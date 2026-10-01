<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnquiryController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'car' => ['nullable', 'string', 'max:120'],
            'interest' => ['nullable', 'string', 'max:120'],
        ], [
            'name.required' => 'Add your name so the studio knows who to call.',
            'phone.required' => 'Add your phone number so the studio can call you.',
        ]);

        $enquiry = Enquiry::create($data);

        // Email the enquiry to the address in config/site.php (needs mail settings in .env)
        try {
            $body = "New enquiry from the website\n\n"
                ."Name: {$enquiry->name}\nPhone: {$enquiry->phone}\nCity: {$enquiry->city}\n"
                ."Car: {$enquiry->car}\nInterested in: {$enquiry->interest}\n"
                .'Received: '.$enquiry->created_at->format('d M Y, h:i A');
            Mail::raw($body, function ($m) use ($enquiry) {
                $m->to(config('site.email'))->subject('New enquiry: '.($enquiry->interest ?: 'Website').' — '.$enquiry->name);
            });
        } catch (\Throwable $e) {
            Log::warning('Enquiry email not sent: '.$e->getMessage());
        }

        $message = 'Thank you. Our team will call you shortly.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->to(route('home').'#contact')->with('status', $message);
    }

    public function index(): View
    {
        return view('admin.enquiries', [
            'enquiries' => Enquiry::latest()->paginate(50),
        ]);
    }
}
