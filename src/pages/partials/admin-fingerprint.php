<?php
$modeLabels = [
    'review'   => 'Review',
    'balanced' => 'Balanced',
    'strict'   => 'Strict',
];
?>
<section class="upload-card" style="margin-top:1.5rem;">
    <h2>Empreintes de vote</h2>

    <?php if (!$isAdmin): ?>
        <p style="color:var(--text-muted,#9ca3af);margin-top:.5rem;">
            Entrez un token d'administration valide pour acceder aux outils de recherche et au mode de securite.
        </p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin-top:1rem;">
            <div style="padding:.9rem 1rem;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.02);">
                <small style="display:block;color:var(--text-muted,#9ca3af);margin-bottom:.25rem;">Evenements conserves</small>
                <strong style="font-size:1.2rem;"><?= (int) $fingerprintMetrics['events'] ?></strong>
            </div>
            <div style="padding:.9rem 1rem;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.02);">
                <small style="display:block;color:var(--text-muted,#9ca3af);margin-bottom:.25rem;">Bloques (24h)</small>
                <strong style="font-size:1.2rem;"><?= (int) $fingerprintMetrics['blockedLast24h'] ?></strong>
            </div>
            <div style="padding:.9rem 1rem;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.02);">
                <small style="display:block;color:var(--text-muted,#9ca3af);margin-bottom:.25rem;">Profils cookies</small>
                <strong style="font-size:1.2rem;"><?= (int) $fingerprintMetrics['profiles'] ?></strong>
            </div>
            <div style="padding:.9rem 1rem;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.02);">
                <small style="display:block;color:var(--text-muted,#9ca3af);margin-bottom:.25rem;">Mode actif</small>
                <strong style="font-size:1.2rem;"><?= htmlspecialchars($modeLabels[$fingerprintMode] ?? $fingerprintMode) ?></strong>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-top:1rem;">
            <form method="POST" action="/admin-settings" style="padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.02);display:grid;gap:.75rem;">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <h3 style="margin:0 0 .35rem;">Mode de securite</h3>
                    <p style="margin:0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                        Review journalise les signaux, Balanced bloque IP+UA+langue, Strict ajoute les prefixes reseau et le contexte navigateur.
                    </p>
                </div>
                <label style="display:grid;gap:.35rem;">
                    <span style="font-size:.92rem;color:var(--text-muted,#9ca3af);">Protection anti-doublon</span>
                    <select name="vote_security_mode" class="token-input" style="width:100%;">
                        <?php foreach ($modeLabels as $modeValue => $modeLabel): ?>
                            <option value="<?= htmlspecialchars($modeValue) ?>" <?= $fingerprintMode === $modeValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($modeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn-upload" style="justify-self:start;">Enregistrer</button>
            </form>

            <form method="GET" style="padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.02);display:grid;gap:.75rem;">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <div>
                    <h3 style="margin:0 0 .35rem;">Recherche inverse</h3>
                    <p style="margin:0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                        Recherchez un User-Agent, une IP ou une valeur de cookie pour retrouver les signaux associes.
                    </p>
                </div>
                <div style="display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:.6rem;">
                    <select name="fingerprint_type" class="token-input" style="width:100%;">
                        <option value="ip" <?= $fingerprintType === 'ip' ? 'selected' : '' ?>>IP</option>
                        <option value="ua" <?= $fingerprintType === 'ua' ? 'selected' : '' ?>>User-Agent</option>
                        <option value="cookie" <?= $fingerprintType === 'cookie' ? 'selected' : '' ?>>Cookie</option>
                    </select>
                    <input
                        class="token-input"
                        type="text"
                        name="fingerprint_query"
                        placeholder="Valeur brute a rechercher..."
                        value="<?= htmlspecialchars($fingerprintQuery) ?>">
                    <div style="display:flex;gap:.45rem;">
                        <button type="submit" class="btn-upload">Chercher</button>
                        <?php if ($fingerprintQuery !== ''): ?>
                            <a href="?token=<?= urlencode($token) ?>&letter=<?= urlencode($letter) ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="btn-upload" style="background:var(--surface3,#2b3542);color:var(--text-muted,#9ca3af);">✕</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($fingerprintResults !== null): ?>
            <div style="margin-top:1rem;padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.02);">
                <h3 style="margin-top:0;">Resultats de recherche</h3>
                <?php if (!empty($fingerprintResults['error'])): ?>
                    <div class="flash flash-error" style="margin-bottom:0;">✗ <?= htmlspecialchars($fingerprintResults['error']) ?></div>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin:.75rem 0 1rem;">
                        <div style="padding:.8rem;border-radius:12px;background:rgba(255,255,255,.03);">
                            <small style="display:block;color:var(--text-muted,#9ca3af);">Autorises</small>
                            <strong><?= (int) ($fingerprintResults['counts']['allowed'] ?? 0) ?></strong>
                        </div>
                        <div style="padding:.8rem;border-radius:12px;background:rgba(255,255,255,.03);">
                            <small style="display:block;color:var(--text-muted,#9ca3af);">Retires</small>
                            <strong><?= (int) ($fingerprintResults['counts']['removed'] ?? 0) ?></strong>
                        </div>
                        <div style="padding:.8rem;border-radius:12px;background:rgba(255,255,255,.03);">
                            <small style="display:block;color:var(--text-muted,#9ca3af);">Bloques</small>
                            <strong><?= (int) ($fingerprintResults['counts']['blocked'] ?? 0) ?></strong>
                        </div>
                        <div style="padding:.8rem;border-radius:12px;background:rgba(255,255,255,.03);">
                            <small style="display:block;color:var(--text-muted,#9ca3af);">Matches</small>
                            <strong><?= count($fingerprintResults['events'] ?? []) ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($fingerprintResults['normalized'])): ?>
                        <p style="margin-top:0;color:var(--text-muted,#9ca3af);">
                            Normalise: <strong><?= htmlspecialchars($fingerprintResults['normalized']) ?></strong>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($fingerprintResults['profile'])): ?>
                        <?php $profile = $fingerprintResults['profile']; ?>
                        <div style="padding:.9rem 1rem;border-radius:14px;background:rgba(255,255,255,.03);margin-bottom:1rem;">
                            <strong>Profil cookie <?= htmlspecialchars($profile['cookieMask'] ?? 'unknown') ?></strong>
                            <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                Votes: <?= (int) ($profile['stats']['votes'] ?? 0) ?>,
                                bloques: <?= (int) ($profile['stats']['blockedAttempts'] ?? 0) ?>,
                                IPs liees: <?= count($profile['linkedIps'] ?? []) ?>,
                                UAs lies: <?= count($profile['linkedUas'] ?? []) ?>,
                                pays: <?= count($profile['linkedCountries'] ?? []) ?>.
                            </p>
                            <?php if (!empty($profile['recentTargets'])): ?>
                                <p style="margin:.45rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                    Cibles recentes: <?= htmlspecialchars(implode(', ', $profile['recentTargets'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1rem;">
                        <div>
                            <strong style="display:block;margin-bottom:.4rem;">Cookies associes</strong>
                            <div style="color:var(--text-muted,#9ca3af);font-size:.94rem;"><?= htmlspecialchars(implode(', ', $fingerprintResults['siblings']['cookies'] ?? [])) ?: 'Aucun' ?></div>
                        </div>
                        <div>
                            <strong style="display:block;margin-bottom:.4rem;">IPs associees</strong>
                            <div style="color:var(--text-muted,#9ca3af);font-size:.94rem;"><?= htmlspecialchars(implode(', ', $fingerprintResults['siblings']['ips'] ?? [])) ?: 'Aucune' ?></div>
                        </div>
                        <div>
                            <strong style="display:block;margin-bottom:.4rem;">User-Agents associes</strong>
                            <div style="color:var(--text-muted,#9ca3af);font-size:.94rem;"><?= htmlspecialchars(implode(' | ', $fingerprintResults['siblings']['uas'] ?? [])) ?: 'Aucun' ?></div>
                        </div>
                    </div>

                    <?php if (!empty($fingerprintResults['siblings']['targets'])): ?>
                        <p style="margin-top:0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                            Cibles vues:
                            <?php foreach ($fingerprintResults['siblings']['targets'] as $targetName => $targetCount): ?>
                                <span style="display:inline-block;margin-right:.5rem;"><?= htmlspecialchars($targetName) ?> (<?= (int) $targetCount ?>)</span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($fingerprintResults['events'])): ?>
                        <div style="display:grid;gap:.75rem;">
                            <?php foreach ($fingerprintResults['events'] as $event): ?>
                                <?php $signals = is_array($event['signals'] ?? null) ? $event['signals'] : []; ?>
                                <div style="padding:.9rem 1rem;border-radius:14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);">
                                    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                                        <strong><?= htmlspecialchars((string) ($event['username'] ?? '')) ?> / <?= htmlspecialchars((string) ($event['direction'] ?? '')) ?></strong>
                                        <span style="color:var(--text-muted,#9ca3af);font-size:.92rem;"><?= htmlspecialchars((string) ($event['timestamp'] ?? '')) ?></span>
                                    </div>
                                    <p style="margin:.4rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                        Outcome: <strong><?= htmlspecialchars((string) ($event['outcome'] ?? 'allowed')) ?></strong>
                                        <?php if (!empty($event['matchLevel']) && $event['matchLevel'] !== 'none'): ?>
                                            • match <?= htmlspecialchars((string) $event['matchLevel']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($event['searchMatch'])): ?>
                                            • recherche <?= htmlspecialchars((string) $event['searchMatch']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <p style="margin:.4rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                        Cookie <?= htmlspecialchars((string) ($signals['cookieMask'] ?? 'unknown')) ?> •
                                        IP <?= htmlspecialchars((string) ($signals['ipMask'] ?? 'unknown')) ?> •
                                        UA <?= htmlspecialchars((string) ($signals['uaLabel'] ?? 'Unknown')) ?>
                                    </p>
                                    <?php if (!empty($signals['acceptLanguageLabel']) || !empty($signals['country'])): ?>
                                        <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                            Langue <?= htmlspecialchars((string) ($signals['acceptLanguageLabel'] ?? 'n/a')) ?>
                                            <?php if (!empty($signals['country'])): ?>
                                                • Pays <?= htmlspecialchars((string) $signals['country']) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($event['flags'])): ?>
                                        <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                            Flags: <?= htmlspecialchars(implode(', ', array_values(array_filter((array) $event['flags'], 'is_string')))) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin:0;color:var(--text-muted,#9ca3af);">Aucun evenement correspondant.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:1rem;padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.02);">
            <h3 style="margin-top:0;">Incidents recents</h3>
            <?php if ($fingerprintIncidents): ?>
                <div style="display:grid;gap:.75rem;">
                    <?php foreach ($fingerprintIncidents as $incident): ?>
                        <?php $signals = is_array($incident['signals'] ?? null) ? $incident['signals'] : []; ?>
                        <div style="padding:.9rem 1rem;border-radius:14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);">
                            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                                <strong><?= htmlspecialchars((string) ($incident['username'] ?? '')) ?> / <?= htmlspecialchars((string) ($incident['direction'] ?? '')) ?></strong>
                                <span style="color:var(--text-muted,#9ca3af);font-size:.92rem;"><?= htmlspecialchars((string) ($incident['timestamp'] ?? '')) ?></span>
                            </div>
                            <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                <?= htmlspecialchars((string) ($incident['outcome'] ?? 'allowed')) ?>
                                <?php if (!empty($incident['matchLevel']) && $incident['matchLevel'] !== 'none'): ?>
                                    • <?= htmlspecialchars((string) $incident['matchLevel']) ?>
                                <?php endif; ?>
                                • <?= htmlspecialchars((string) ($signals['ipMask'] ?? 'unknown')) ?>
                                • <?= htmlspecialchars((string) ($signals['uaLabel'] ?? 'Unknown')) ?>
                            </p>
                            <?php if (!empty($incident['flags'])): ?>
                                <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                    Flags: <?= htmlspecialchars(implode(', ', array_values(array_filter((array) $incident['flags'], 'is_string')))) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="margin:0;color:var(--text-muted,#9ca3af);">Aucun incident recent.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($fingerprintMetrics['topProfiles'])): ?>
            <div style="margin-top:1rem;padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.02);">
                <h3 style="margin-top:0;">Profils a surveiller</h3>
                <div style="display:grid;gap:.75rem;">
                    <?php foreach ($fingerprintMetrics['topProfiles'] as $profile): ?>
                        <div style="padding:.9rem 1rem;border-radius:14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);">
                            <strong><?= htmlspecialchars((string) ($profile['cookieMask'] ?? 'unknown')) ?></strong>
                            <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                Votes <?= (int) ($profile['stats']['votes'] ?? 0) ?> •
                                bloques <?= (int) ($profile['stats']['blockedAttempts'] ?? 0) ?> •
                                reset soupconnes <?= (int) ($profile['stats']['cookieResetMatches'] ?? 0) ?> •
                                burst/min <?= (int) ($profile['stats']['burstVotesPerMinute'] ?? 0) ?>
                            </p>
                            <p style="margin:.35rem 0 0;color:var(--text-muted,#9ca3af);font-size:.94rem;">
                                IP fanout <?= (int) ($profile['stats']['ipFanout'] ?? 0) ?> •
                                UA fanout <?= (int) ($profile['stats']['uaFanout'] ?? 0) ?> •
                                repeat denials <?= (int) ($profile['stats']['repeatDenials'] ?? 0) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
