<x-public.layout title="Privacy Policy">
    <div class="mx-auto max-w-2xl px-6 py-16 sm:py-24">
        <header class="mb-10">
            <p class="text-sm font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                {{ config('app.name', 'Atelier') }}
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Privacy Policy</h1>
            <p class="mt-3 text-zinc-500 dark:text-zinc-400">
                How {{ config('app.name', 'Atelier') }} handles the personal data of people who leave feedback on a project.
            </p>
        </header>

        <div class="space-y-8 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">What we collect</h2>
                <p>
                    Viewing a project is anonymous. If you choose to leave a comment, we ask for your
                    <strong>name</strong> and <strong>email address</strong>. We collect nothing else about
                    you, and no analytics or tracking is in use.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">Why we collect it, and the legal basis</h2>
                <p>
                    Your name is shown next to your feedback so the studio knows who said what. Your email
                    is the stable identifier that lets us re-attribute your comments across visits and reach
                    you about them; it is <strong>never shown to other people</strong>. We rely on our
                    <strong>legitimate interest</strong> in running a feedback tool, engaged only when you
                    actively choose to comment.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">The “remember me” cookie</h2>
                <p>
                    When you leave your first comment we set a cookie named <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">atelier_commenter</code>
                    on your device so you don’t have to re-enter your details on a return visit. It stores only
                    a reference to your commenting identity, lasts up to a year, and is set solely as a result
                    of you choosing to comment. Clearing your browser cookies removes it.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">How long we keep it</h2>
                <p>
                    We keep your details {{ config('atelier.privacy.retention') }}
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">Your rights</h2>
                <p>
                    You can ask us to access, correct, or erase your personal data at any time. On an erasure
                    request we anonymise your identity and redact the comments you authored, while keeping the
                    surrounding discussion intact.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">Contact</h2>
                <p>
                    For any data request, email
                    <a href="mailto:{{ config('atelier.privacy.contact_email') }}" class="font-medium underline">{{ config('atelier.privacy.contact_email') }}</a>.
                </p>
            </section>
        </div>

        <div class="mt-12 border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <a href="{{ url()->previous() }}" class="text-sm text-zinc-500 underline hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                ← Back
            </a>
        </div>
    </div>
</x-public.layout>
