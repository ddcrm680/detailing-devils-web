<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnquiryController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! config('services.enquiry.submission_enabled', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Enquiry submissions are temporarily unavailable. Please try again later.',
            ], 503);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:50'],
            'car' => ['nullable', 'string', 'max:120'],
            'interest' => ['nullable', 'string', 'max:120'],
        ], [
            'name.required' => 'Add your name so the studio knows who to call.',
            'phone.required' => 'Add your phone number so the studio can call you.',
        ]);

        $summary = [];
        if (filled($data['car'] ?? null)) {
            $summary[] = 'Vehicle: '.$data['car'];
        }
        if (filled($data['interest'] ?? null)) {
            $summary[] = 'Interested in: '.$data['interest'];
        }

        try {
            $inserted = DB::table('tbl_SaveContactForm7_8')->insert([
                'first_name' => $data['name'],
                'last_name' => null,
                'your_phone' => $data['phone'],
                'city' => $data['city'] ?? null,
                'car_model' => $data['car'] ?? null,
                'interested_in' => $data['interest'] ?? null,
                'TypeMessage' => $summary ? implode("\n", $summary) : null,
                'source' => 'website-home',
                'query_type' => 'service-enquiry',
            ]);
            if (! $inserted) {
                throw new \RuntimeException('CRM insert returned false.');
            }
        } catch (\Throwable $e) {
            Log::error('Enquiry CRM insert failed', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'message' => 'We could not send your enquiry right now. Please try again later or call us.',
            ], 503);
        }

        if (config('services.enquiry.email_enabled', false)) {
            // Email the enquiry to the address in config/site.php (needs mail settings in .env)
            try {
                $body = "New enquiry from the website\n\n"
                    ."Name: {$data['name']}\nPhone: {$data['phone']}\nCity: ".($data['city'] ?? '')."\n"
                    .'Car: '.($data['car'] ?? '')."\nInterested in: ".($data['interest'] ?? '')."\n"
                    .'Received: '.now()->format('d M Y, h:i A');
                Mail::raw($body, function ($m) use ($data) {
                    $m->to(config('site.email'))->subject('New enquiry: '.(($data['interest'] ?? null) ?: 'Website').' — '.$data['name']);
                });
            } catch (\Throwable $e) {
                Log::warning('Enquiry email not sent', ['exception' => $e::class]);
            }
        }

        $message = 'Thank you. Our team will call you shortly.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->to(route('home').'#contact')->with('status', $message);
    }
}
