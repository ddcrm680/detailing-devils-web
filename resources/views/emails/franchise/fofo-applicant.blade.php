<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('site.brand') }}</title>
    <style>
        .bg-table {
            background-repeat: no-repeat !important;
            background-position: left center !important;
            background-size: 100% auto !important;
            background-color: #F6F6F7 !important;
        }
        .link-hover:hover { color: #dd3333 !important; }
    </style>
</head>
<body>
    <div style="margin:0 auto;max-width:680px;width:100%;">
        <div style="max-width:680px;width:100%;">
            <div style="margin:0 auto;text-align:center;width:100%;background:#F6F6F7;padding:15px 10px 10px 10px;">
                <a href="https://www.detailingdevils.com" title="{{ config('site.brand') }}" target="_blank"><img src="{{ asset('img/dd-logo-top.png') }}" alt="{{ config('site.brand') }}" height="70" style="max-width:100%;"></a>
            </div>
            <div class="bg-table" style="max-width:680px;width:100%;padding:10px;">
                <div style="max-width:590px;margin:0 auto;">
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">Hi {{ $name }},</p>
                    <h3 style="margin-top:0px;margin-bottom:8px;font-size:16px;color:#222;font-family:helvetica;line-height:25px;">Greetings from {{ \Illuminate\Support\Str::beforeLast(config('site.brand'), ' ') }} <span style="color:red;">{{ \Illuminate\Support\Str::afterLast(config('site.brand'), ' ') }}</span>!!!</h3>
                    <p style="margin-top:0px;margin-bottom:10px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">Thank you for showing interest in becoming a channel partner with {{ config('site.brand') }}.</p>
                    <p style="margin-top:0px;margin-bottom:10px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">For your detailed understanding we have linked our <a href="{{ asset('pdf/Franchise-Proposal-2024.pdf') }}" target="_blank" style="color:red;" class="link-hover">Franchise Proposal, Product &amp; Service Catalog</a> in this email, kindly go through it.</p>

                    <h3 style="margin-top:15px;margin-bottom:5px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Who We <span style="color:red;">Are</span></h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">{{ config('site.brand') }} is a service oriented detailing company, specializing in car coatings, Paint Protection Films, Graphene coatings, expert in cleaning, coating and protecting vehicles belonging to every category.<br>
                    {{ config('site.brand') }} invites individuals with entrepreneurial zeal and passion for automotive customization to join the {{ config('site.brand') }} squad and become a part of an ever-growing and one of the best detailing solutions in India.</p>

                    <h3 style="margin-top:15px;margin-bottom:5px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">What We <span style="color:red;">Do</span></h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">We provide the best automobile detailing services to our customers. They consist :</p>
                    <ul style="padding-left:15px;">
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Nano Ceramic Coating:</b> Vehicle coating with protective nano ceramics</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Detailing:</b> Vehicle paint &amp; different area’s restoration through SRS</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>PPF:</b> {{ config('site.brand') }} PPF is expertly designed to deliver the highest level of protection from minor to medium scratches.</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>DNA Graphene:</b> It creates a superb and ultra-flexible coating that can be applied to almost every surface that exists.</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Washing:</b> Detailed washing of a vehicle through different methods</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Cleaning:</b> Professional vehicle cleaning with world-class equipment</li>
                    </ul>

                    <h3 style="margin-top:15px;margin-bottom:5px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Why <span style="color:red;">Us</span>?</h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">{{ config('site.brand') }} not only provides quality services but it also focuses on providing the ultimate post-service experience. That is why we are popular with a huge base of happy customers and franchise partners in just a short span of time.</p>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">{{ config('site.brand') }} provides complete support to its franchise partners in areas of techniques, set-up, marketing and training that will ensure you progress with our franchise. Our franchise opportunity is available for all regions in India.</p>
                    <p style="margin-top:0px;margin-bottom:5px;font-size:16px;color:#222;font-family:helvetica;line-height:25px;"><b>Benefits of {{ config('site.brand') }} Franchise</b></p>
                    <ul style="margin-bottom:10px;margin-top:0;padding-left:15px;">
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Established brand name and franchise business in India</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>{{ config('site.numbers.0.value') }}</b> International and National studios</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">High &amp; quick return on investment</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Marketing assistance</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Regular &amp; Advanced training</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Online Management Tools</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Technical &amp; Backend Support</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">exclusively researched ceramic coatings</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">World-class equipment</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;">Opportunity starting at just 35 lakhs</li>
                    </ul>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">Our franchise partner can expect full support from our end so they can provide flawless services to our customers.</p>

                    <h3 style="margin-top:15px;margin-bottom:0px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Our Franchise <span style="color:red;">Requirements</span></h3>
                    <ul style="margin-bottom:10px;margin-top:10px;padding-left:15px;">
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Basic Cost:</b> 35 lacs</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Minimum Area:</b> 1500 - 1800 square feet (Ground Floor)</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Minimum Inventory:</b> Rs 30,000/- Monthly</li>
                    </ul>
                    <h3 style="margin-top:15px;margin-bottom:5px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Investment Includes</h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">It Comprises of all the basics needs to start &amp; run a detailing studio i.e. Tools, Machinery, Equipment, Signage, Ceramic Coating kits, PPF rolls, Graphene Coatins, Cleaning &amp; paint correction materials, Training, PAN India Marketing Support, Backend Support, Marketing Material.</p>
                    <h3 style="margin-top:15px;margin-bottom:0px;font-size:19px;color:#222;font-family:helvetica;line-height:25px;">Franchisee Additional Expenses (Other than Total Investment)</h3>
                    <ul style="margin-bottom:10px;margin-top:10px;padding-left:15px;">
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Infrastructure Development:</b> Civil Work, interior, Electrical work etc (Approximate Cost : ₹ 700 per sq ft )</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Signage:</b> Acrylic front Signage</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Branding:</b> Interior Exterior Branding of Studio</li>
                        <li style="color:#222;font-family:helvetica;line-height:25px;font-size:14px;"><b>Miscellaneous Expenses:</b> Material shipping charges</li>
                    </ul>

                    <h3 style="margin-top:15px;margin-bottom:5px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Our <span style="color:red;">Address</span></h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">For a franchise meeting, please visit our head office at the following address:</p>
                    <h4 style="margin-top:0px;margin-bottom:0px;font-size:16px;color:#222;font-family:helvetica;line-height:25px;">{{ config('site.brand') }} Head Office Address:</h4>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">{{ config('site.address') }}</p>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">Please feel free to call us at below mentioned number for any further query or clarification.<br>Thank you!<br>Best regards,</p>
                    <h3 style="margin-top:0px;margin-bottom:10px;font-size:20px;color:#222;font-family:helvetica;line-height:25px;">Franchise Team</h3>
                    <h3 style="margin-top:0px;margin-bottom:5px;font-size:20px;color:red;font-family:helvetica;line-height:25px;">{{ config('site.brand') }}</h3>
                    <p style="margin-top:0px;margin-bottom:15px;font-size:14px;color:#222;font-family:helvetica;line-height:25px;">Phone: <a href="tel:{{ config('site.phone_link') }}" style="color:red;text-decoration:none;">{{ config('site.phone') }}</a><br>
                        Email: <a href="mailto:{{ config('site.email') }}" style="color:red;text-decoration:none;">{{ config('site.email') }}</a><br>
                        Whatsapp: <a target="_blank" href="https://api.whatsapp.com/send?phone=918076767898" style="color:#25D366;text-decoration:none;">+91 8076 767 898</a><br>
                        W: <a href="https://www.detailingdevils.com" target="_blank" style="color:red;text-decoration:none;">www.detailingdevils.com</a>
                    </p>
                    <p>
                        <a href="{{ config('site.social.Facebook') }}" target="_blank" style="width:40px;height:40px;display:inline-block;border-radius:5px;overflow:hidden;"><img src="{{ asset('img/email/p1.png') }}" alt="Facebook" width="35" height="35"></a>
                        <a href="{{ config('site.social.Instagram') }}" target="_blank" style="width:40px;height:40px;display:inline-block;border-radius:5px;overflow:hidden;"><img src="{{ asset('img/email/p2.png') }}" alt="Instagram" width="35" height="35"></a>
                        <a href="{{ config('site.social.YouTube') }}" target="_blank" style="width:40px;height:40px;display:inline-block;border-radius:5px;overflow:hidden;"><img src="{{ asset('img/email/p3.png') }}" alt="YouTube" width="35" height="35" style="height:35px;"></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
