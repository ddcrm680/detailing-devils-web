@extends('layouts.app')

@section('title', 'Refund and Returns Policy | Detailing Devils')
@section('description', 'Read the Detailing Devils refund and returns policy, including return eligibility and the return and exchange processes.')

@section('content')
<div class="legal-page">
    <header class="legal-page__hero">
        <div class="legal-page__hero-inner">
            <span class="lbl">Detailing Devils / Legal</span>
            <h1>Refund and Returns Policy</h1>
        </div>
    </header>

    <article class="legal-page__body">
        <p>If you are unhappy with your purchase, please let us know and we will gladly accept returns or exchanges within 10 days of purchase. Just send us an email to <strong><a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></strong> and include your order number and reason for return/Exchanges.</p>

        <p>As per the return policy, once a product is delivered, can only be returned if its’ damaged, defective or not as shown on the website.</p>

        <p>If the product falls in any of the above category, you can request for replacement within the prescribed return time. In some rare or special case, complete refund will be provided once the defective product is received by the seller.</p>

        <h2>Return Process:</h2>
        <p>In case, there is any problem concerning the product, our team shall coordinate in verifying and resolving any issue related to it. You will shortly receive a call and an email from our product return verification assistant. If the problem still remains unresolved, the seller shall then replace the product.<br>However, if the product is out of stock the seller shall completely refund the amount paid.</p>

        <h2>Exchange Process</h2>
        <p><strong>Before Pick-Up, Our Team Would Check For The Given Things:</strong></p>
        <ul>
            <li>The product is correct or not as far as the brand name, bar code, image and MRP is concerned. Moreover, everything should be properly visible.</li>
            <li>The product should be complete with all the freebies and combos if any.</li>
            <li>There should not be any tampering with the warranty or quality check seals.</li>
            <li>The product should not be damaged or torn with any dents or hole.</li>
            <li>The original packaging of the product should remain the same.</li>
        </ul>
        <p><strong>Note:</strong> Only under the above conditions, the product shall be entitled for any exchange.</p>
    </article>
</div>
@endsection
