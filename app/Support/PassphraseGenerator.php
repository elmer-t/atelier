<?php

namespace App\Support;

/**
 * Builds memorable, sentence-style gate passwords (diceware-style, e.g.
 * `correct-horse-battery-staple`) so a Creator can generate a strong password with
 * one click and read it aloud to a Client (#43). The words are short, common and
 * unambiguous; four of them comfortably clear the raised gate-password minimum while
 * staying easy to relay. The value drops into the same validation/hashing path as a
 * typed password — nothing here bypasses it.
 */
class PassphraseGenerator
{
    /**
     * A curated, deliberately plain wordlist. All lowercase, no digits, no ambiguous
     * pairs — every entry survives being read down a phone line.
     *
     * @var list<string>
     */
    public const WORDS = [
        'amber', 'anchor', 'apple', 'arrow', 'autumn', 'bamboo', 'basket', 'beacon',
        'bear', 'bird', 'blossom', 'boulder', 'branch', 'bridge', 'bright', 'bronze',
        'brook', 'bubble', 'button', 'cabin', 'cactus', 'candle', 'canyon', 'carbon',
        'castle', 'cedar', 'cherry', 'clever', 'cloud', 'clover', 'coast', 'cobalt',
        'coffee', 'comet', 'copper', 'coral', 'cotton', 'cradle', 'crane', 'crimson',
        'crystal', 'cyan', 'daisy', 'dawn', 'delta', 'desert', 'diamond', 'dolphin',
        'dragon', 'dream', 'drift', 'eagle', 'ember', 'emerald', 'engine', 'falcon',
        'feather', 'fern', 'ferry', 'fiber', 'field', 'flame', 'flint', 'flower',
        'forest', 'fossil', 'fountain', 'foxglove', 'frost', 'galaxy', 'garden', 'ginger',
        'glacier', 'granite', 'grove', 'hammer', 'harbor', 'harvest', 'hazel', 'heron',
        'hollow', 'honey', 'horizon', 'island', 'ivory', 'jasmine', 'jetty', 'jungle',
        'juniper', 'kettle', 'lagoon', 'lantern', 'ledger', 'lemon', 'lily', 'linen',
        'lotus', 'lumber', 'maple', 'marble', 'meadow', 'meteor', 'mint', 'mirror',
        'mist', 'moss', 'mountain', 'nectar', 'nettle', 'oasis', 'ocean', 'olive',
        'onyx', 'opal', 'orbit', 'orchid', 'otter', 'pebble', 'pepper', 'petal',
        'pigeon', 'pilot', 'pine', 'planet', 'plaza', 'pollen', 'pond', 'poppy',
        'portal', 'prairie', 'quartz', 'quiver', 'rabbit', 'rain', 'raven', 'reef',
        'ribbon', 'ridge', 'river', 'robin', 'rocket', 'rose', 'rust', 'saddle',
        'saffron', 'sage', 'salmon', 'sand', 'sapphire', 'satin', 'shadow', 'shell',
        'silk', 'silver', 'sky', 'slate', 'snow', 'sparrow', 'spice', 'spring',
        'sprout', 'spruce', 'stone', 'storm', 'stream', 'summer', 'sunset', 'swallow',
        'tangerine', 'teal', 'thistle', 'thunder', 'tiger', 'timber', 'topaz', 'trail',
        'tulip', 'tundra', 'turtle', 'valley', 'velvet', 'violet', 'walnut', 'water',
        'wave', 'willow', 'window', 'winter', 'wombat', 'yarrow', 'zephyr', 'zinnia',
    ];

    /**
     * A `separator`-joined passphrase of `words` random words from the list.
     */
    public function generate(int $words = 4, string $separator = '-'): string
    {
        $count = count(self::WORDS);
        $picked = [];

        for ($i = 0, $n = max(1, $words); $i < $n; $i++) {
            $picked[] = self::WORDS[random_int(0, $count - 1)];
        }

        return implode($separator, $picked);
    }
}
