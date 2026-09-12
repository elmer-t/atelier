<?php

use App\Livewire\Public\ArtifactComments;
use App\Models\Artifact;
use App\Models\Project;
use Livewire\Livewire;

it('serves the privacy policy page', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('atelier_commenter')
        ->assertSee(config('atelier.privacy.contact_email'));
});

/**
 * The packaged contact address is a placeholder — this repository is public — so the
 * policy has to publish whatever the deployment configures instead. Without this, a
 * hardcoded address would pass the assertion above and still send data-subject requests
 * into a mailbox nobody reads.
 */
it('publishes the deployment-configured data-protection contact', function () {
    config(['atelier.privacy.contact_email' => 'dpo@studio.test']);

    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('dpo@studio.test')
        ->assertDontSee('privacy@example.com');
});

/**
 * The landing page is the single entry point to the policy; the project page and the
 * feedback rail deliberately no longer carry their own link.
 */
it('links the privacy policy from the landing page only', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('privacy'));

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertDontSee(route('privacy'));
});

/**
 * The notice has to travel with the fields. The rail only asks for a name and email once a
 * visitor has picked a spot to comment on, so the notice is asserted at that moment — the
 * point the details are actually requested — rather than on an idle rail that asks nothing.
 */
it('shows a point-of-capture privacy notice at the identity form', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->assertSet('identified', false)
        ->set('draftAnchor', ['type' => 'text_range', 'quote' => 'Brief'])
        ->assertSee('Add your details to comment')
        ->assertSee('your email is never shown to others', escape: false)
        ->assertDontSeeHtml(route('privacy'));
});
