<?php

namespace Database\Seeders;

use App\Enums\ProjectVisibility;
use App\Enums\UserRole;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * PROTOTYPE / DEMO DATA — safe to wipe.
 *
 * Gives "project 1" (Harbor District Masterplan) real, readable markdown artifacts
 * and a realistic spread of feedback threads so the comment rail has genuine prose to
 * anchor against and genuine threads to show. Every anchor quote below is an exact
 * substring of the artifact body so its stage highlight actually renders.
 *
 *   php artisan db:seed --class=PrototypeCommentsSeeder
 */
class PrototypeCommentsSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('title', 'Harbor District Masterplan')->first()
            ?? Project::query()->where('visibility', ProjectVisibility::Public)->orderBy('id')->firstOrFail();

        $author = User::query()->where('role', UserRole::Creator)->first()
            ?? User::factory()->create(['role' => UserRole::Creator]);

        // Replace the lorem artifacts with curated prose, but keep the header cover.
        $project->artifacts()
            ->when($project->header_artifact_id, fn ($q) => $q->whereKeyNot($project->header_artifact_id))
            ->get()
            ->each->delete();

        $commenters = $this->commenters();

        foreach ($this->artifacts() as $order => $spec) {
            $artifact = Artifact::factory()
                ->for($project)
                ->markdown($spec['body'])
                ->create(['title' => $spec['title'], 'sort_order' => $order]);

            $artifact->revisions()->update(['user_id' => $author->id]);

            // Stagger threads back in time so "newest first" has something to sort by
            // and the bylines read realistically (oldest first in the list => newest last).
            $count = count($spec['threads']);

            foreach ($spec['threads'] as $i => $thread) {
                $rootAge = now()->subDays(($count - $i) * 2)->subHours($i * 3);
                $this->seedThread($artifact, $thread, $commenters, $author, $rootAge);
            }
        }
    }

    /**
     * @param  array{quote: string, body: string, by: string, resolved?: bool, replies?: list<array{by: string, body: string}>}  $thread
     * @param  array<string, User>  $commenters
     */
    private function seedThread(Artifact $artifact, array $thread, array $commenters, User $author, CarbonInterface $rootAge): void
    {
        $root = Comment::factory()
            ->for($artifact)
            ->create([
                'user_id' => $commenters[$thread['by']]->id,
                'body' => $thread['body'],
                'anchor' => ['type' => 'text_range', 'quote' => $thread['quote']],
                'resolved_at' => ($thread['resolved'] ?? false) ? $rootAge->addDay() : null,
                'resolved_by' => ($thread['resolved'] ?? false) ? $author->id : null,
                'created_at' => $rootAge,
                'updated_at' => $rootAge,
            ]);

        foreach ($thread['replies'] ?? [] as $r => $reply) {
            $author_id = $reply['by'] === 'Studio' ? $author->id : $commenters[$reply['by']]->id;
            $replyAge = $rootAge->addHours(($r + 1) * 5);

            Comment::factory()->replyTo($root)->create([
                'user_id' => $author_id,
                'body' => $reply['body'],
                'created_at' => $replyAge,
                'updated_at' => $replyAge,
            ]);
        }
    }

    /**
     * Real people leaving the feedback, found-or-created as passwordless Clients.
     *
     * @return array<string, User>
     */
    private function commenters(): array
    {
        $people = [
            'Mara' => 'mara.velasquez@harborcity.gov',
            'Devin' => 'devin.okafor@stonepath.dev',
            'Priya' => 'priya.raman@meridianstudio.com',
        ];

        $users = [];

        foreach ($people as $name => $email) {
            $users[$name] = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name === 'Mara' ? 'Mara Velasquez' : ($name === 'Devin' ? 'Devin Okafor' : 'Priya Raman'), 'role' => UserRole::Client, 'password' => null],
            );
        }

        return $users;
    }

    /**
     * @return list<array{title: string, body: string, threads: list<array{quote: string, body: string, by: string, resolved?: bool, replies?: list<array{by: string, body: string}>}>}>
     */
    private function artifacts(): array
    {
        return [
            [
                'title' => 'Vision & Design Principles',
                'body' => <<<'MD'
# Vision & Design Principles

The Harbor District will reconnect the city to its working waterfront — a place that has spent forty years behind fences and freight. Our ambition is a mixed, resilient neighbourhood that earns its keep every hour of the day, not a showpiece that empties at dusk.

## Five principles

1. **Water first.** Every street should end in a view of the water, and the water's edge belongs to the public before it belongs to any building.
2. **Fine grain over big blocks.** Small parcels, many doorways, and short blocks keep the ground floor lively and let the district change one building at a time.
3. **Build for weather that is coming, not weather that was.** Ground floors sit above the 2100 flood line and the whole district drains toward planted basins rather than pipes.
4. **Mix uses on every block.** Homes above workshops above cafés — no single-use towers, no dead frontages.
5. **Keep what works.** The grain silos, the crane rails, and the cobbled freight yard are not obstacles to design around; they are the reason anyone will want to be here.

## The character we are aiming for

Think less "luxury waterfront" and more "a place that was always here and simply woke up." Materials should weather honestly, planting should look a little wild, and the lighting at night should be warm and low rather than corporate and even.

We are explicitly avoiding a single architectural language. A masterplan that dictates one façade treatment produces a district that reads as a single project by a single hand — exactly the monotony we are trying to escape.
MD,
                'threads' => [
                    [
                        'quote' => 'belongs to the public before it belongs to any building',
                        'body' => 'Strongly agree with this as a headline principle. Can we make it a binding requirement in the design code, not just an aspiration? Otherwise the first tower will privatise its frontage.',
                        'by' => 'Mara',
                        'replies' => [
                            ['by' => 'Studio', 'body' => 'Yes — we\'ll write a minimum 12m public setback into the code for every waterfront parcel. Adding to the Public Realm doc.'],
                            ['by' => 'Mara', 'body' => 'Perfect. That\'s the one thing the council will check first.'],
                        ],
                    ],
                    [
                        'quote' => 'Ground floors sit above the 2100 flood line',
                        'body' => 'Which flood scenario is this — the RCP 8.5 upper bound or the central estimate? The number changes the plinth height by almost a metre and that ripples through every ground-floor section.',
                        'by' => 'Devin',
                    ],
                    [
                        'quote' => 'The grain silos, the crane rails, and the cobbled freight yard',
                        'body' => 'Love this. The silos especially — they could be the single most memorable thing in the district if we resist the urge to over-restore them.',
                        'by' => 'Priya',
                        'resolved' => true,
                    ],
                ],
            ],
            [
                'title' => 'Public Realm & Waterfront',
                'body' => <<<'MD'
# Public Realm & Waterfront

The public realm is the project. Buildings will come and go over the next fifty years, but the streets, the quay, and the tidal park are the permanent civic gesture — the part we cannot get wrong.

## The continuous quay

A single continuous walk runs the full 1.4 kilometres of water's edge, uninterrupted by any private plot. Where a building meets the quay it does so with an active ground floor — a boatyard, a market hall, a swimming club — never a blank service wall or a fenced garden.

The quay steps down to the water in broad timber terraces, so that at low tide people can sit almost at the waterline, and at spring high tide the lowest terrace is allowed to flood. This is deliberate: the district should feel the tide rather than hide from it.

## The tidal park

At the northern end, the old dry dock becomes a tidal park — a salt marsh that floods and drains twice a day, cleaning stormwater before it reaches the harbour and giving the district a genuinely wild edge. Boardwalks thread through it; nothing is manicured.

## Materials and planting

- Paving is reclaimed granite sett from the freight yard, relaid, with new granite only where we must.
- Trees are salt-tolerant and storm-hardy: hackberry, hornbeam, and black pine, planted in continuous soil trenches rather than pits.
- Lighting is low, warm, and shielded — bright enough to feel safe, dark enough to keep the harbour sky.

## Open question

We have not yet resolved how service and delivery vehicles reach the market hall without breaking the continuous quay. A timed-access shared surface is the current assumption, but it needs testing against real delivery patterns.
MD,
                'threads' => [
                    [
                        'quote' => 'at spring high tide the lowest terrace is allowed to flood',
                        'body' => 'This is lovely in principle but I can already hear the maintenance team. What is the anti-slip strategy on timber that is wet twice a day? We had exactly this fight at Riverside and lost two years to it.',
                        'by' => 'Devin',
                        'replies' => [
                            ['by' => 'Studio', 'body' => 'Grooved hardwood with a broadcast-grit finish on the two lowest terraces only. We\'ll spec it and add a replacement schedule so it\'s budgeted, not a surprise.'],
                        ],
                    ],
                    [
                        'quote' => 'the old dry dock becomes a tidal park',
                        'body' => 'This is the best idea in the whole masterplan. Please protect it in phase one — if it slips to a later phase it will get value-engineered away.',
                        'by' => 'Priya',
                    ],
                    [
                        'quote' => 'A timed-access shared surface is the current assumption',
                        'body' => 'Flagging that the market hall tenants will push hard for all-day access. We should model a 6–10am delivery window and see if the tenant mix can live with it before we commit.',
                        'by' => 'Mara',
                    ],
                ],
            ],
            [
                'title' => 'Phasing & Delivery',
                'body' => <<<'MD'
# Phasing & Delivery

A masterplan that only works when fully built is a masterplan that never gets built. Each phase here has to stand on its own — a complete, inhabitable piece of the district — so that if funding stalls after phase one, what exists is still a real place and not a building site with hoardings.

## Phase one (years 1–3)

The tidal park, the first 400 metres of quay, and two mixed-use blocks around the market hall. This is the smallest move that creates a destination: somewhere to walk to, something to do when you arrive, and homes for the first few hundred residents.

## Phase two (years 3–6)

The central blocks and the primary school. Housing numbers roughly triple. This is where the district has to prove it can be an ordinary neighbourhood and not just a weekend attraction.

## Phase three (years 6–10)

The silo conversions and the southern blocks. We deliberately leave the silos late: they are the hardest and most expensive pieces, and by phase three the district will have the footfall to make a bold cultural use viable.

## Delivery risks we are watching

- **Land assembly** in the southern blocks depends on two parcels still in private hands.
- **The silo conversions** carry the most cost uncertainty and should not sit on the critical path for housing delivery.
- **Infrastructure first** — the drainage basins and the raised ground must be built before any vertical construction, which front-loads cost into phase one.
MD,
                'threads' => [
                    [
                        'quote' => 'if funding stalls after phase one, what exists is still a real place',
                        'body' => 'This is exactly the right test and it is rare to see it stated so plainly. Every phase boundary in the drawings should be checked against it.',
                        'by' => 'Mara',
                        'resolved' => true,
                    ],
                    [
                        'quote' => 'We deliberately leave the silos late',
                        'body' => 'I understand the cost logic, but the silos are the identity of the place. Leaving them to year six means six years of the district\'s best asset sitting empty behind a fence. Can we at least do a lightweight meanwhile-use in phase one?',
                        'by' => 'Priya',
                        'replies' => [
                            ['by' => 'Devin', 'body' => 'Agree with Priya. A cheap meanwhile-use (events, market, climbing) de-risks the eventual conversion by proving demand.'],
                            ['by' => 'Studio', 'body' => 'Good push. We\'ll add a meanwhile-use line to phase one and note it as identity insurance, not just activation.'],
                        ],
                    ],
                    [
                        'quote' => 'the raised ground must be built before any vertical construction',
                        'body' => 'Front-loading the earthworks into phase one is going to be a hard sell to the funders. Do we have a fallback if phase one\'s budget can\'t absorb all of it?',
                        'by' => 'Devin',
                    ],
                ],
            ],
        ];
    }
}
