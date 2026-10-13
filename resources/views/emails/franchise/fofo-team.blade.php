<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New FOFO Franchise Enquiry</title>
</head>
<body style="margin:0;padding:20px 10px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
    <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #ddd;">
        <div style="padding:20px 25px;background:#111;color:#fff;">
            <h1 style="margin:0;font-size:22px;">New {{ config('site.brand') }} FOFO Franchise Enquiry</h1>
        </div>
        <div style="padding:25px;">
            <h2 style="color:#b78a18;font-size:18px;">Applicant Details</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:38%;text-align:left;padding:8px;">Name</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $name }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Mobile</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $mobile }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Email</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['email'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Country</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $savedLead->country }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">State</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $savedLead->state }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">City</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $savedLead->city }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Date of birth</th><td style="padding:8px;">{{ $application['dob'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Profession</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['profession'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Preferred franchise city</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['franchiseapply'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Preferred contact</th><td style="padding:8px;">{{ $application['contacted'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">How they heard about us</th><td style="padding:8px;">{{ $application['hearabout'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Start timeline</th><td style="padding:8px;">{{ $application['wantstart'] }}</td></tr>
            </table>

            <h2 style="color:#b78a18;font-size:18px;">Business and Property</h2>
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
                <tr><th style="width:38%;text-align:left;padding:8px;">New business reason</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['newbussiness'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Existing business</th><td style="padding:8px;">{{ $application['bussinessowner'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Business type</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['type_of_bussiness'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Products or services</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['product_service'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Business description</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['bussiness_description'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Automotive experience</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['experienceautomotive'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Experience details</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['experience'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Existing automotive facility</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['existingautomotive'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Facility details</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['existingfacility'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Property available</th><td style="padding:8px;">{{ $application['spacearound'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Property details</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['elaboratelocation'] ?? '-' }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Location image</th><td style="padding:8px;overflow-wrap:anywhere;">@if ($imageUrl)<a href="{{ $imageUrl }}">View location image</a>@else Not provided @endif</td></tr>
                <tr><th style="text-align:left;padding:8px;">Time available</th><td style="padding:8px;">{{ $application['spendfranchise'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Civil litigation</th><td style="padding:8px;">{{ $application['civillitigation'] }}</td></tr>
                <tr><th style="text-align:left;padding:8px;">Website</th><td style="padding:8px;overflow-wrap:anywhere;">{{ $application['website'] ?? '-' }}</td></tr>
            </table>
            <p><strong>Submitted On:</strong> {{ $submittedOn }}</p>
            <p style="border-top:1px solid #ddd;padding-top:15px;color:#777;font-size:12px;text-align:center;">This is an automated notification from the {{ config('site.brand') }} FOFO Franchise page.</p>
        </div>
    </div>
</body>
</html>
