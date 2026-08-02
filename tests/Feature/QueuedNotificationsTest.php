<?php

use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Notifications\ArtifactCommentPosted;
use App\Notifications\ReplyOnYourThread;
use Illuminate\Contracts\Queue\ShouldQueue;

it('queues the comment notification so a slow push endpoint cannot block the request', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();
    $comment = Comment::factory()->for($artifact)->create();

    expect(new ArtifactCommentPosted($comment))->toBeInstanceOf(ShouldQueue::class);
});

it('queues the reply notification', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create();
    $reply = Comment::factory()->for($artifact)->create();

    expect(new ReplyOnYourThread($reply))->toBeInstanceOf(ShouldQueue::class);
});
