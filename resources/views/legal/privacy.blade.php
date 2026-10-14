@extends('layouts.app')

@section('title', 'Privacy Policy | Detailing Devils')
@section('description', 'Read the Detailing Devils privacy policy on personal information, log data, communications, cookies and security.')

@section('content')
<div class="legal-page">
    <header class="legal-page__hero">
        <div class="legal-page__hero-inner">
            <span class="lbl">Detailing Devils / Legal</span>
            <h1>Privacy Policy</h1>
        </div>
    </header>

    <article class="legal-page__body">
        <p class="legal-page__intro">A BLEND OF PROTECTION &amp; PERFECTION.</p>

        <p>Detailing Devils (“us”, “we”, or “our”) operates <a href="{{ route('home') }}">www.detailingdevils.com</a> (the “Site”). This page informs you of our policies regarding the collection, use and disclosure of Personal Information we receive from users of the Site.</p>

        <p>We use your personal information only for providing and improving the site. By using the site, you agree to the collection and use of information in accordance with this policy.</p>

        <h2>Information Collection and Use</h2>
        <p>While using our site, we may ask you to provide us with certain personally identifiable information that can be used to contact or identify you. Personally identifiable information may include, but is not confined to your name.</p>

        <h2>Log Data</h2>
        <p>Like many site operators, we collect information that your browser sends whenever you visit our site.<br>This log data may include information such as your computer’s Internet Protocol (“IP”) address, browser type, browser version, and the pages of our site that you visit, the time and date of your visit, the time spent on those pages and other statistics.</p>

        <h2>Communications</h2>
        <p>We may use your Personal Information to contact you with newsletters, marketing or promotional materials and other information.</p>

        <h2 id="cookies">Cookies</h2>
        <p>Cookies are files with small amount of data, which may include an anonymous unique identifier. Cookies are sent to your browser from a web site and stored on your computer’s hard drive. Like many sites, we use “cookies” to collect information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our site. For the convenience of our users, We cache our pages to improve the performance of our website. This cookie is set if a page need to bypass the cache and deliver a fresh content. This shall augment the loading time of the webpages and be convenient for the users.</p>

        <h2>Security</h2>
        <p>The security of your personal information is important to us, but remember that no method of transmission over the Internet, or method of electronic storage, is 100% secure. While we strive to use commercially acceptable means to protect your personal information, we cannot guarantee its absolute security.</p>

        <h2>Changes to This Privacy Policy</h2>
        <p>This Privacy Policy is effective as of January 2017 and will remain in effect except with respect to any changes in its provisions in the future, which will be in effect immediately after being posted on this page.</p>
        <p>We reserve the right to update or change our Privacy Policy at any time and you should check this Privacy Policy periodically. Your continued use of the Service after we post any modifications to the Privacy Policy on this page will constitute your acknowledgment of the modifications and your consent to abide and be bound by the modified Privacy Policy.</p>
        <p>If we make any material changes to this Privacy Policy, we will notify you either through the email address you have provided us, or by placing a prominent notice on our website.</p>

        <h2>Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us.</p>

        <h2>Address:</h2>
        <address>
            <p>D-50, Sector 2, Noida,<br>Uttar Pradesh, India - 201301<br><a href="tel:+919555695695">+91 9555 695 695</a><br><a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></p>
        </address>

        <p>Need help?<br><a href="https://www.stagingweb.detailingdevils.com/tips">READ OUR COATING MAINTENANCE TIPS</a></p>
    </article>
</div>
@endsection
