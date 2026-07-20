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

it('links the privacy policy from the public index and project page', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('privacy'));

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee(route('privacy'));
});

it('shows a point-of-capture privacy notice at the identity form', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();

    Livewire::test(ArtifactComments::class, ['artifact' => $artifact])
        ->assertSet('identified', false)
        ->assertSee('Privacy Policy')
        ->assertSeeHtml(route('privacy'));
});
