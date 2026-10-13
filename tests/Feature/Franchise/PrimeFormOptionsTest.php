<?php

namespace Tests\Feature\Franchise;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PrimeFormOptionsTest extends TestCase
{
    public function test_prime_page_renders_configured_dropdown_values_and_labels_in_order(): void
    {
        $xpath = $this->primePageXPath();

        $states = array_map(
            fn (string $state): array => ['value' => $state, 'label' => $state],
            config('form_options.common.states'),
        );

        foreach ([
            'state' => $states,
            'age_group' => config('form_options.prime.age_groups'),
            'occupation' => config('form_options.prime.occupations'),
            'start_timeline' => config('form_options.prime.timelines'),
        ] as $id => $expected) {
            $actual = [];

            foreach ($xpath->query("//select[@id='{$id}']/option") as $option) {
                if ($option->getAttribute('value') !== '') {
                    $actual[] = ['value' => $option->getAttribute('value'), 'label' => trim($option->textContent)];
                }
            }

            $this->assertSame($expected, $actual, "Options for {$id} changed.");
        }

        $this->assertSame('18–24 years', config('form_options.prime.age_groups.0.label'));
        $this->assertSame('18-24', config('form_options.prime.age_groups.0.value'));
        $this->assertSame('Within 1–3 months', config('form_options.prime.timelines.1.label'));
        $this->assertSame('Within 1-3 months', config('form_options.prime.timelines.1.value'));
    }

    public function test_fixed_choices_keep_their_original_ids_values_and_five_step_structure(): void
    {
        $xpath = $this->primePageXPath();

        foreach ([
            'business_experience' => 'business_experience',
            'property_status' => 'property_status',
            'road_facing' => 'road_facing',
            'decision_maker' => 'decision_maker',
            'contact_methods[]' => 'contact_methods',
        ] as $name => $configGroup) {
            $actual = [];

            foreach ($xpath->query("//input[@name='{$name}']") as $input) {
                $actual[] = ['id' => $input->getAttribute('id'), 'value' => $input->getAttribute('value')];
            }

            $expected = array_map(
                fn (array $option): array => ['id' => $option['id'], 'value' => $option['value']],
                config("form_options.prime.{$configGroup}"),
            );

            $this->assertSame($expected, $actual, "Choices for {$name} changed.");
        }

        $this->assertSame(['business_yes', 'business_no'], array_column(config('form_options.prime.business_experience'), 'id'));
        $this->assertSame(['property_yes', 'property_no', 'property_rent'], array_column(config('form_options.prime.property_status'), 'id'));
        $this->assertSame(5, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " form-step ")]')->length);
    }

    public function test_prime_submission_requires_a_valid_application(): void
    {
        config()->set('services.prime.submission_enabled', true);

        $this->postJson(route('prime.submit'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'mobile_number']);
    }

    public function test_disabled_prime_page_keeps_all_five_steps_but_disables_submission(): void
    {
        config()->set('services.prime.submission_enabled', false);
        $xpath = $this->primePageXPath();

        $this->assertSame(5, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " form-step ")]')->length);
        $this->assertSame('false', $xpath->query('//form[@id="franchiseForm"]')->item(0)->getAttribute('data-submission-enabled'));
        $this->assertTrue($xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " btn-submit ")]')->item(0)->hasAttribute('disabled'));
        $this->assertSame(1, $xpath->query('//*[contains(text(), "Application preview.")]')->length);
    }

    public function test_enabled_prime_page_retains_submission_action(): void
    {
        config()->set('services.prime.submission_enabled', true);
        $xpath = $this->primePageXPath();

        $this->assertSame('true', $xpath->query('//form[@id="franchiseForm"]')->item(0)->getAttribute('data-submission-enabled'));
        $this->assertFalse($xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " btn-submit ")]')->item(0)->hasAttribute('disabled'));
        $this->assertSame(0, $xpath->query('//*[contains(text(), "Application preview.")]')->length);
    }

    private function primePageXPath(): DOMXPath
    {
        $response = $this->get(route('prime.show'))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
