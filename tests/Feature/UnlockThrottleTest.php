<?php

use App\Models\Project;

it('throttles unlock attempts against one project even as the source IP rotates', function () {
    $project = Project::factory()->private('correct-horse-battery')->create();

    // Ten guesses spread across ten different addresses — the per-IP limit alone would
    // never bite. The per-slug cap is what must stop the eleventh (#43).
    $lastStatus = null;

    foreach (range(1, 11) as $i) {
        $lastStatus = $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(route('project.unlock', $project), ['password' => 'wrong-guess'])
            ->getStatusCode();
    }

    expect($lastStatus)->toBe(429);
});

it('does not throttle a normal single unlock', function () {
    $project = Project::factory()->private('correct-horse-battery')->create();

    $this->post(route('project.unlock', $project), ['password' => 'correct-horse-battery'])
        ->assertRedirect(route('project.show', $project));
});
