@extends('layouts.app')

@section('title', 'Cancellation Policy | Detailing Devils')
@section('description', 'Read the Detailing Devils cancellation policy, including cancellation periods, refunds, processed orders and exceptions.')

@section('content')
<div class="legal-page">
    <header class="legal-page__hero">
        <div class="legal-page__hero-inner">
            <span class="lbl">Detailing Devils / Legal</span>
            <h1>Cancellation Policy</h1>
        </div>
    </header>

    <article class="legal-page__body">
        <p>Thank you for shopping with The Detailing Devils! We strive to provide you with the best shopping experience possible. Please review our cancellation policy outlined below:</p>

        <ul>
            <li><strong>Cancellation Period : </strong> Customers may cancel their order within 24 hours of placing it, provided that the order has not yet been processed or shipped.</li>
            <li><strong>Cancellation Process : </strong> To cancel an order, please contact our customer service team at email – <strong><a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></strong> and <strong>+91 9555695695 </strong>with your order details.</li>
            <li><strong>Refunds : </strong> If the cancellation request is made within the stipulated cancellation period and the order has not been processed, a full refund will be issued to the original payment method within 5 – 7 business days.</li>
            <li><strong>Cancellation After Processing : </strong> Orders that have already been processed or shipped cannot be canceled. In such cases, customers may return the item(s) per our return policy.</li>
            <li><strong>Return Policy : </strong> Please refer to our return policy for information on returning items after receiving them.</li>
            <li><strong>Exceptions : </strong> The Detailing Devils reserves the right to refuse or cancel any order for any reason, including but not limited to product unavailability, inaccuracies in product or pricing information, or fraudulent activity. In such cases, customers will be notified and provided with a full refund.</li>
            <li><strong>Policy Updates : </strong> We reserve the right to modify or update this cancellation policy at any time without prior notice. Any changes will be effective immediately upon posting to our website.</li>
        </ul>

        <p><strong>Note:</strong> Only under the above conditions, the product shall be entitled for any exchange.</p>
    </article>
</div>
@endsection
