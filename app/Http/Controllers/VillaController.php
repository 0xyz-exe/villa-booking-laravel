<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Reservation;
use App\Models\SeasonalRate;
use App\Models\Villa;
use App\Models\VillaReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['check_in' => 'nullable|date|after:today', 'check_out' => 'nullable|date|after:check_in', 'guests' => 'nullable|integer|min:1|max:100', 'location' => 'nullable|string|max:120']);
        $villas = Villa::with(['amenities', 'seasonalRates'])->where('active', true)
            ->when($data['guests'] ?? null, fn ($q, $n) => $q->where('guest_capacity', '>=', $n))
            ->when($data['location'] ?? null, fn ($q, $l) => $q->where('location', 'like', '%'.$l.'%'))
            ->when(isset($data['check_in'], $data['check_out']), fn ($q) => $q->whereDoesntHave('reservations', fn ($r) => $r->where('status', 'confirmed')->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in'])))
            ->orderBy('name')->get();
        return view('villas.index', compact('villas'));
    }

    public function show(Villa $villa)
    {
        abort_unless($villa->active, 404);
        return view('villas.show', ['villa' => $villa->load(['amenities', 'seasonalRates', 'reviews.guest'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['villa_id' => 'required|integer|exists:villas,id', 'check_in' => 'required|date|after:today', 'check_out' => 'required|date|after:check_in', 'guests' => 'required|integer|min:1|max:100']);
        $checkIn = Carbon::parse($data['check_in'])->startOfDay(); $checkOut = Carbon::parse($data['check_out'])->startOfDay();
        $nights = (int) $checkIn->diffInDays($checkOut);
        $booking = DB::transaction(function () use ($request, $data, $checkIn, $checkOut, $nights) {
            $villa = Villa::whereKey($data['villa_id'])->lockForUpdate()->firstOrFail();
            if (!$villa->active || $data['guests'] > $villa->guest_capacity || $nights < $villa->minimum_stay) {
                throw ValidationException::withMessages(['villa_id' => 'Villa is inactive, guest capacity exceeded, or minimum stay not met.']);
            }
            if ($villa->reservations()->where('status', 'confirmed')->where('check_in', '<', $checkOut->toDateString())->where('check_out', '>', $checkIn->toDateString())->exists()) {
                throw ValidationException::withMessages(['check_in' => 'Villa is already reserved for these dates.']);
            }
            $rates = $villa->seasonalRates()->where('starts_on', '<', $checkOut->toDateString())->where('ends_on', '>', $checkIn->toDateString())->get();
            for ($date = $checkIn->copy(); $date->lt($checkOut); $date->addDay()) {
                if ($rates->where('starts_on', '<=', $date->toDateString())->where('ends_on', '>', $date->toDateString())->count() > 1) {
                    throw ValidationException::withMessages(['check_in' => 'Overlapping seasonal rates are misconfigured; booking unavailable.']);
                }
            }
            $total = 0;
            for ($date = $checkIn->copy(); $date->lt($checkOut); $date->addDay()) {
                $season = $rates->first(fn ($rate) => $rate->starts_on->toDateString() <= $date->toDateString() && $rate->ends_on->toDateString() > $date->toDateString());
                $total += (float) ($season?->nightly_rate ?? $villa->nightly_rate);
            }
            return Reservation::create(['reference' => Str::upper(Str::random(12)), 'villa_id' => $villa->id, 'guest_id' => $request->user()->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => $data['guests'], 'nights' => $nights, 'total_price' => number_format($total, 2, '.', ''), 'status' => 'confirmed']);
        });
        return redirect()->route('reservations.show', $booking)->with('status', 'Booking confirmed.');
    }

    public function confirmation(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->guest_id === $request->user()->id || $reservation->villa->owner_id === $request->user()->id || $request->user()->is_admin, 403);
        return view('villas.confirmation', ['reservation' => $reservation->load('villa')]);
    }

    public function mine(Request $request)
    {
        return view('villas.mine', ['reservations' => $request->user()->reservations()->with(['villa', 'review'])->latest()->get()]);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->guest_id === $request->user()->id, 403);
        abort_unless($reservation->status === 'confirmed' && $reservation->check_in->isFuture(), 422);
        $reservation->update(['status' => 'cancelled']);
        return back()->with('status', 'Booking cancelled.');
    }

    public function review(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->guest_id === $request->user()->id, 403);
        abort_unless($reservation->status === 'completed' && $reservation->check_out->isPast() && !$reservation->review, 422);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:1000']);
        VillaReview::create(['reservation_id' => $reservation->id, 'guest_id' => $request->user()->id] + $data);
        return back()->with('status', 'Review submitted.');
    }

    public function dashboard(Request $request)
    {
        $villas = Villa::withCount(['reservations as confirmed_bookings' => fn ($q) => $q->where('status', 'confirmed')])
            ->withSum(['reservations as confirmed_revenue' => fn ($q) => $q->where('status', 'confirmed')], 'total_price')
            ->when(!$request->user()->is_admin, fn ($q) => $q->where('owner_id', $request->user()->id))->orderBy('name')->get();
        $ids = $villas->modelKeys();
        $reservations = Reservation::with(['villa', 'guest'])->whereIn('villa_id', $ids)->latest()->get();
        $amenities = Amenity::orderBy('name')->get();
        return view('villas.dashboard', compact('villas', 'reservations', 'amenities'));
    }

    public function saveVilla(Request $request, ?Villa $villa = null)
    {
        if ($villa?->exists) \Illuminate\Support\Facades\Gate::authorize('manage-villa', $villa);
        $data = $request->validate(['name' => 'required|string|max:120', 'location' => 'required|string|max:160', 'description' => 'nullable|string|max:3000', 'guest_capacity' => 'required|integer|min:1|max:100', 'nightly_rate' => 'required|numeric|min:0|max:999999', 'minimum_stay' => 'required|integer|min:1|max:365', 'amenities' => 'nullable|array', 'amenities.*' => 'integer|exists:amenities,id', 'image' => 'nullable|image|max:2048']);
        $amenityIds = $data['amenities'] ?? []; unset($data['amenities']);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('villas', 'public'); unset($data['image']);
        if ($villa?->exists) { $villa->update($data); $villa->amenities()->sync($amenityIds); }
        else { $villa = Villa::create($data + ['owner_id' => $request->user()->id]); $villa->amenities()->sync($amenityIds); }
        return back()->with('status', 'Villa saved.');
    }

    public function seasonalRate(Request $request)
    {
        $data = $request->validate(['villa_id' => 'required|exists:villas,id', 'starts_on' => 'required|date|after:today', 'ends_on' => 'required|date|after:starts_on', 'nightly_rate' => 'required|numeric|min:0|max:999999']);
        $villa = Villa::findOrFail($data['villa_id']); \Illuminate\Support\Facades\Gate::authorize('manage-villa', $villa);
        if ($villa->seasonalRates()->where('starts_on', '<', $data['ends_on'])->where('ends_on', '>', $data['starts_on'])->exists()) throw ValidationException::withMessages(['starts_on' => 'Seasonal rates cannot overlap.']);
        SeasonalRate::create($data);
        return back()->with('status', 'Seasonal rate saved.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-villa', $reservation->villa);
        $data = $request->validate(['status' => 'required|in:confirmed,cancelled,completed']);
        if ($data['status'] === 'confirmed' && $reservation->status !== 'confirmed') {
            DB::transaction(function () use ($reservation) {
                $villa = Villa::whereKey($reservation->villa_id)->lockForUpdate()->firstOrFail();
                if ($villa->reservations()->where('status', 'confirmed')->where('id', '!=', $reservation->id)->where('check_in', '<', $reservation->check_out)->where('check_out', '>', $reservation->check_in)->exists()) {
                    throw ValidationException::withMessages(['status' => 'Villa has a conflicting confirmed booking.']);
                }
                $reservation->update(['status' => 'confirmed']);
            });
        } else $reservation->update($data);
        return back()->with('status', 'Booking status updated.');
    }

    public function report(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $villas = Villa::query()->when(!$request->user()->is_admin, fn ($q) => $q->where('owner_id', $request->user()->id))->pluck('id');
        $rows = Reservation::with(['villa'])->whereIn('villa_id', $villas)->where('status', 'confirmed')
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('check_in', '>=', $d))->when($data['to'] ?? null, fn ($q, $d) => $q->where('check_in', '<=', $d))->orderBy('check_in')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['booking_reference', 'villa', 'check_in', 'check_out', 'nights', 'guests', 'status', 'revenue']);
            foreach ($rows as $r) fputcsv($out, [$r->reference, $r->villa->name, $r->check_in->toDateString(), $r->check_out->toDateString(), $r->nights, $r->guests, $r->status, $r->total_price]); fclose($out);
        }, 'villa-bookings.csv', ['Content-Type' => 'text/csv']);
    }
}
