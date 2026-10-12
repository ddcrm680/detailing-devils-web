<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Prime Franchise Enquiry</title>
</head>
<body style="margin:0;padding:20px 10px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
    <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #ddd;">
        <div style="padding:20px 25px;background:#111;color:#fff;">
            <h1 style="margin:0;font-size:22px;">New Detailing Devils Prime Franchise Enquiry</h1>
            <p style="margin:8px 0 0;font-size:13px;color:#d9d9d9;">A new franchise enquiry has been received. The applicant details are provided below.</p>
        </div>
        <div style="padding:25px;">
            <h2 style="color:#b78a18;font-size:18px;">Applicant Details</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">Name</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $name }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Mobile</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $mobile }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">WhatsApp</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $whatsapp }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Email</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['email'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Age Group</th><td style="padding:8px;">{{ $application['age_group'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Occupation</th><td style="padding:8px;">{{ $application['occupation'] }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Location</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">State</th><td style="padding:8px;">{{ $application['state'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">City</th><td style="padding:8px;">{{ $application['city'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Current Address</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $currentAddress }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Business Background</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">Business Experience</th><td style="padding:8px;">{{ $application['business_experience'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Business Name</th><td style="padding:8px;overflow-wrap:anywhere;">{{ filled($application['business_name'] ?? null) ? $application['business_name'] : '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Industry</th><td style="padding:8px;overflow-wrap:anywhere;">{{ filled($application['industry'] ?? null) ? $application['industry'] : '-' }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Property Details</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">Property Status</th><td style="padding:8px;">{{ $application['property_status'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Property Details</th><td style="padding:8px;overflow-wrap:anywhere;">{{ filled($propertyDetails) ? $propertyDetails : '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Carpet Area</th><td style="padding:8px;">{{ filled($application['carpet_area'] ?? null) ? $application['carpet_area'] : '-' }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Franchise Preferences</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">Preferred City</th><td style="padding:8px;overflow-wrap:anywhere;">{{ filled($application['preferred_city'] ?? null) ? $application['preferred_city'] : '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Preferred Area</th><td style="padding:8px;overflow-wrap:anywhere;">{{ filled($application['preferred_area'] ?? null) ? $application['preferred_area'] : '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Start Timeline</th><td style="padding:8px;">{{ $application['start_timeline'] }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Additional Details</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:35%;text-align:left;padding:8px;">Decision Maker</th><td style="padding:8px;">{{ $application['decision_maker'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Preferred Contact</th><td style="padding:8px;">{{ $contactMethods }}</td></tr>
            </table>
            <p><strong>Reason for Partnership</strong></p>
            <div style="padding:12px;background:#f8f8f8;border-left:4px solid #d4a017;white-space:pre-wrap;overflow-wrap:anywhere;">{{ filled($application['partnership_reason'] ?? null) ? $application['partnership_reason'] : '-' }}</div>
            <p><strong>Consent Given:</strong> {{ ! empty($application['consent']) ? 'Yes' : 'No' }}</p>
            <p><strong>Submitted On:</strong> {{ $submittedOn }}</p>
            <p style="border-top:1px solid #ddd;padding-top:15px;color:#777;font-size:12px;text-align:center;">This is an automated notification from the Detailing Devils Prime Franchise page.</p>
        </div>
    </div>
</body>
</html>
