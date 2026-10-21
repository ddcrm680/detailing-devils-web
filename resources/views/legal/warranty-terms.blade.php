@extends('layouts.app')

@section('title', 'Warranty Terms & Conditions | Detailing Devils')
@section('description', 'Read the Detailing Devils warranty terms and conditions, including coverage, maintenance requirements, exclusions and the warranty claim procedure.')

@push('css')
<style>
    .legal-page__body h2.warranty-terms__heading {
        font-size: 24px;
        line-height: 1.35;
        margin: 1.75em 0 .65em;
        padding-top: .75em;
    }

    @media (max-width: 760px) {
        .legal-page__body h2.warranty-terms__heading {
            font-size: 20px;
            line-height: 1.4;
        }
    }
</style>
@endpush

@section('content')
<div class="legal-page">
    <header class="legal-page__hero">
        <div class="legal-page__hero-inner">
            <span class="lbl">Detailing Devils / Legal</span>
            <h1>Warranty Terms &amp; Conditions</h1>
        </div>
    </header>

    <article class="legal-page__body">
        <p class="legal-page__intro">Detailing Devils warranty is valid only if the service is availed from an authorized studio &amp; is registered online.</p>

        <h2 class="warranty-terms__heading">1. Armor Reload, Armor Pro, Armor Absolute &amp; Armor Graphene</h2>
        <ul>
            <li>Warranty is provided only for the shine of the vehicle’s painted body. 1, 3 and, 5 years for Armor Reload, Armor Pro, and Armor Absolute respectively and 6 years for Armor Graphene.</li>
            <li>After application, avoid washing/cleaning your vehicle for 2 days. Bring your vehicle after 2 days for a Complimentary first wash. (Note: only 1st wash is complimentary).</li>
            <li>1st complimentary ceramic wash can be availed within a period of 10 days from the date of treatment. No requests will be entertained afterwards.</li>
            <li>First car assessment should be done after one year of treatment and thereafter, once a year.</li>
            <li>During each maintenance visit, washing of a vehicle will be chargeable worth Rs 500/-</li>
            <li>Yearly-assessment can be availed before 30 days or after 30 days from the year completion date. No requests will be entertained after the period expires.</li>
        </ul>

        <h2 class="warranty-terms__heading">2. Paint Protection Film (PPF)</h2>
        <ul>
            <li>A 7 Year Warranty is provided for Paint Protection Film (2 year warranty for self healing and 7 year warranty for shine, pealing and cracks).</li>
            <li>First car assessment should be done after one year of treatment and thereafter, once a year.</li>
            <li>Yearly-assessment can be availed before 30 days or after 30 days from the year completion date. No requests will be entertained after the period expires.</li>
        </ul>

        <h2 class="warranty-terms__heading">3. Warranty will be considered void if:</h2>
        <ul>
            <li>If your warranty is not registered online, in that case contact the studio from where service have been availed immediately &amp; get it registered.</li>
            <li>If vehicle is washed/cleaned within 2 days after application of coating. (Only in Armor Reload, Armor Pro, Armor Absolute, and Armor Graphene).</li>
            <li>Coating or Paint Protection Film is either removed or over coated with any other product(s).</li>
            <li>Soiling, damages, discoloration or stains of coatings or Paint Protection Film caused due to bleaches, solvents, acid, burns, dye, accident, vandalism or negligence/ faults on part of the automobile owner.</li>
            <li>Damage due to accident.</li>
            <li>Scratches due to dry cleaning of the vehicle (Only wet clean the vehicle with plain water).</li>
            <li>Vehicle is repainted. (Only in Armor Reload, Armor Pro, Armor Absolute, and Armor Graphene, Warranty will be considered void for repainted part(s) or entire exterior paint body depending on the paint job)</li>
        </ul>

        <p><strong>Note :</strong> Customer can get their vehicle coated and covered with Paint Protection Film again (whether repainted/damaged by accident) by paying appropriate charges.</p>

        <h2 class="warranty-terms__heading">4. To uphold the authenticity of the warranty, follow the instructions and consult an authorized Detailing Devils studio.</h2>

        <h2 class="warranty-terms__heading">5. Detailing Devils warranty is non-transferable and applicable only after you complete all the registration formalities.</h2>

        <h2 class="warranty-terms__heading">6. In case of any coating or film damage during the warranty period, follow the given procedure:</h2>
        <ul>
            <li>Contact your nearest coating studio right away and inform them about the damage issue.</li>
            <li>The service inspection in charge will then examine your vehicle during the business hours in the nearest coating studio (allotted by them).</li>
            <li>Post assessment if your claims get accepted, then our service inspector will contact you with a mutually convenient time and location</li>
            <li>Provide your Invoice/warranty card to avail warranty.(Warranty shall be considered invalid in case it is not registered online)</li>
            <li>If the claim is not made within 30 days of observing the problem, it will automatically set Detailing Devils dealer free of any legal obligations.</li>
        </ul>

        <p><strong>Disclaimer :</strong> Always make sure to take your invoice and get your warranty registered online from the studio you are availing the service. Detailing Devils gives no warranty and accepts no responsibility or liability if your warranty is not registered online. In that case under no circumstances will Detailing Devils be held responsible or liable in any way for any warranty claims.</p>

        <p><a href="https://www.devilscrm.detailingdevils.com/check-warranty?access=true" target="_blank" rel="noopener">Warranty Check</a></p>

        <p>If you have any questions about warranty please contact us.</p>
        <h2>Address:</h2>
        <address>
            {{ config('site.address') }}<br>
            <a href="tel:{{ config('site.phone_link') }}">{{ config('site.phone') }}</a><br>
            <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
        </address>
    </article>
</div>
@endsection
