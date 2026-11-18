<?php
declare(strict_types=1);

/* Ikony interfejsu (kreskowe), piktogramy znaków D (czarne na białym polu) oraz logo. */

function ikona(string $n, int $r = 22): string
{
    $s = [
        'telefon' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'zegar' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'tak' => '<path d="M20 6 9 17l-5-5"/>',
        'kalendarz' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'trasa' => '<path d="M3 11 22 2l-9 19-2-8z"/>',
        'karta' => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
        'parking' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9.5 17V7h3.2a3 3 0 0 1 0 6H9.5"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    ];
    return '<svg class="ik" width="' . $r . '" height="' . $r . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($s[$n] ?? '') . '</svg>';
}

/** Znak informacyjny jak przy drodze: niebieska tablica, białe pole, czarny piktogram. */
function znak(string $n): string
{
    $kolo = static fn(float $x, float $y, float $r = 5): string => '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '"/>';
    $p = [
        'ciezarowka' => '<g fill="#16181a"><rect x="4" y="13" width="24" height="18" rx="1.5"/><path d="M30 19h7.2c.7 0 1.3.3 1.7.9l4.3 6c.5.7.8 1.5.8 2.3V31H30z"/><rect x="4" y="31" width="40" height="3"/>' . $kolo(13, 36) . $kolo(36, 36) . '</g>'
            . '<g fill="#fff"><path d="M32.5 21.5h4.2l3.6 5h-7.8z"/>' . $kolo(13, 36, 2) . $kolo(36, 36, 2) . '</g>',
        'karetka' => '<g fill="#16181a"><rect x="4" y="15" width="25" height="16" rx="1.5"/><path d="M31 19h6.2c.7 0 1.3.3 1.7.9l4.3 6c.5.7.8 1.5.8 2.3V31H31z"/><rect x="4" y="31" width="40" height="3"/><rect x="12.5" y="9.5" width="8" height="4.5" rx="2"/>' . $kolo(13, 36) . $kolo(36, 36) . '</g>'
            . '<g fill="#fff"><path d="M14.5 17.5h4v3.5H22v4h-3.5v3.5h-4V25H11v-4h3.5z"/><path d="M33.5 21.5h3.2l3.6 5h-6.8z"/>' . $kolo(13, 36, 2) . $kolo(36, 36, 2) . '</g>',
        'taxi' => '<g fill="#16181a"><path d="M5 31v-4.2c0-1 .7-1.9 1.7-2.1l6.8-1.5 4.6-5.7c.6-.7 1.4-1.1 2.3-1.1h9.4c.9 0 1.8.4 2.4 1.2l4.3 5.6 4.4 1.2c1.2.3 2.1 1.5 2.1 2.8V31z"/><rect x="20" y="10.5" width="9.5" height="4.5" rx="1.2"/>' . $kolo(14, 32, 4.8) . $kolo(34.5, 32, 4.8) . '</g>'
            . '<g fill="#fff"><path d="M19.6 19h4.2v4.3h-7.7z"/><path d="M26 19h3.8l3.3 4.3H26z"/>' . $kolo(14, 32, 1.9) . $kolo(34.5, 32, 1.9) . '</g>',
        'wozek' => '<g fill="#16181a"><rect x="31" y="7" width="3.2" height="29"/><path d="M34.2 30.5H45v3H34.2z"/><rect x="36" y="20" width="8" height="9.5"/><path d="M7 27.5c0-1.1.9-2 2-2h19.5c.8 0 1.5.7 1.5 1.5V34H7z"/><path d="M12 25.5V13.2c0-.9.8-1.7 1.7-1.7h10.9c.8 0 1.5.5 1.7 1.3l3 12.7h-2.7L24 14.2h-9.3v11.3z"/>' . $kolo(13.5, 36, 4.6) . $kolo(26, 36, 4.6) . '</g>'
            . '<g fill="#fff">' . $kolo(13.5, 36, 1.8) . $kolo(26, 36, 1.8) . '</g>',
        'prawko' => '<rect x="5.5" y="12.5" width="37" height="23" rx="3.5" fill="none" stroke="#16181a" stroke-width="3"/><g fill="#16181a"><rect x="10.5" y="18" width="10" height="12" rx="1.5"/><rect x="24" y="18" width="14" height="3"/><rect x="24" y="23.5" width="14" height="3"/><rect x="24" y="29" width="9" height="3"/></g>',
        'firma' => '<g fill="#16181a"><path d="M8 40V10.5c0-.8.7-1.5 1.5-1.5h16c.8 0 1.5.7 1.5 1.5V40z"/><path d="M29 40V22.5c0-.8.7-1.5 1.5-1.5h8c.8 0 1.5.7 1.5 1.5V40z"/></g>'
            . '<g fill="#fff"><rect x="11.5" y="13" width="4.5" height="4.5"/><rect x="19" y="13" width="4.5" height="4.5"/><rect x="11.5" y="21" width="4.5" height="4.5"/><rect x="19" y="21" width="4.5" height="4.5"/><rect x="15" y="31" width="5" height="9"/><rect x="32" y="25" width="5" height="4"/><rect x="32" y="32" width="5" height="4"/></g>',
    ];
    if ($n === 'elka') {
        return '<span class="znak znak--l" aria-hidden="true"><svg viewBox="0 0 48 48"><path fill="#fff" d="M15 8h7.5v24.5H34V40H15z"/></svg></span>';
    }
    return '<span class="znak" aria-hidden="true"><svg viewBox="0 0 48 48">' . ($p[$n] ?? '') . '</svg></span>';
}

function logo_svg(int $r = 40): string
{
    return '<svg width="' . $r . '" height="' . $r . '" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" rx="8" fill="#08703f"/><rect x="3.5" y="3.5" width="33" height="33" rx="5.5" fill="none" stroke="#fff" stroke-width="2"/><path d="M20 9.5v21M12.5 11v6.5a7.5 7.5 0 0 0 15 0V11M15.5 30.5h9" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function strzalka_svg(): string
{
    return '<svg class="tablica__strzalka" viewBox="0 0 60 84" aria-hidden="true"><path fill="#fff" d="M30 2 57 36H40v46H20V36H3z"/></svg>';
}
