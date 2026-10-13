<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Prime Franchise Application</title>
</head>
<body style="margin:0;padding:20px 10px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333;">
    <div style="max-width:700px;margin:0 auto;background:#fff;border:1px solid #ddd;">
        <div style="padding:22px 25px;background:#111;text-align:center;">
            <img src="{{ asset('img/dd-logo-top.png') }}" alt="{{ config('site.brand') }}" width="260" style="display:block;margin:0 auto;max-width:100%;height:auto;border:0;">
            <p style="margin:20px 0 0;color:#d9d9d9;font-size:14px;line-height:22px;">Thank you for choosing to explore the <strong style="color:#fff;">{{ config('site.brand') }} Prime Franchise</strong> opportunity.</p>
        </div>
        <div style="padding:30px;line-height:1.75;">
            <h1 style="margin:0 0 22px;color:#111;font-size:26px;">Thank You for Your Franchise Application</h1>
            <p>Dear <strong>{{ $name }}</strong>,</p>
            <p>Greetings from <strong>{{ config('site.brand') }}!</strong></p>
            <p>Thank you for submitting your application for the <strong>{{ config('site.brand') }} Prime Franchise.</strong> We appreciate your interest in partnering with one of India's leading premium car detailing brands.</p>

            <div style="margin:28px 0;padding:18px;background:#f8f8f8;border-left:4px solid #d4a017;">
                <h2 style="margin:0 0 12px;color:#111;font-size:18px;">Download Your Franchise Proposal</h2>
                <p>Your <strong>{{ config('site.brand') }} Prime Franchise Proposal</strong> is now ready to download. It includes complete details about the investment, business model, franchise benefits, operational support, training process and growth opportunities.</p>
                <a href="{{ asset('pdf/Prime-Franchise-Proposal.pdf') }}" target="_blank" rel="noopener" style="display:inline-block;padding:13px 24px;background:#111;color:#fff;text-decoration:none;font-weight:bold;">Download Franchise Proposal</a>
            </div>

            <h2 style="margin:30px 0 12px;color:#b78a18;font-size:20px;">What Happens Next?</h2>
            <div style="padding:18px;background:#fafafa;border:1px solid #eee;">
                <p>✓ Our franchise team will carefully review your application.</p>
                <p>✓ A franchise consultant will contact you shortly to understand your business goals and preferred location.</p>
                <p>✓ We will guide you through the complete onboarding process and answer any questions you may have.</p>
            </div>

            <p>If you have any questions in the meantime, simply reply to this email. Our team will be happy to assist you throughout your franchise journey.</p>
            <p>We look forward to welcoming you to the <strong>{{ config('site.brand') }} Family.</strong></p>
            <p>Warm Regards,<br><strong>Team {{ config('site.brand') }}</strong></p>
            <p style="margin-top:35px;padding-top:18px;border-top:1px solid #eee;text-align:center;color:#777;font-size:12px;">This is an automated email from the <strong>{{ config('site.brand') }} Prime Franchise Team.</strong><br>© {{ now()->year }} {{ config('site.brand') }}. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>
