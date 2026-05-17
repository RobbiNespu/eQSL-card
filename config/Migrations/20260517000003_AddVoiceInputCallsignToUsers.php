<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M5 T29 — `users.voice_input_callsign` opt-in feature flag.
 *
 * When ON, the quick-add form renders a mic button next to the
 * callsign input. Tap it, say the callsign in NATO phonetic
 * ("nine mike two romeo delta x-ray"), and the decoded letters
 * land in the input. Useful for hands-busy portable ops (one hand
 * on the antenna, other on the radio — phone in a chest mount).
 *
 * Default OFF because:
 *  - Web Speech API is Chromium-only (Android Chrome/Edge); Firefox
 *    and iOS Safari have either no support or partial support that
 *    requires explicit on-device recognition flags.
 *  - Recognition routes through Google's cloud on Android Chrome,
 *    which some operators won't want for privacy reasons.
 *  - Accuracy varies — operators should opt in only after testing.
 *
 * Sibling column to `block_dupes_in_activation` (T27). If a third
 * preference lands, consider migrating both into a `user_preferences`
 * JSON column instead of growing the table further.
 */
final class AddVoiceInputCallsignToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('voice_input_callsign', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'block_dupes_in_activation',
                'comment' => 'M5 T29 — when true, /qsos/quick shows a mic button that decodes NATO-phonetic speech into the callsign field.',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('voice_input_callsign')
            ->update();
    }
}
