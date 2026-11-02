@php
    $illustrations = [
        'route' => ['Route not found', 'A coupe on a studio navigation grid, with an interrupted route to a missing destination.', '01 / Route guidance'],
        'access' => ['Studio access restricted', 'A locked studio shutter behind a coupe, representing a restricted entrance.', '02 / Access control'],
        'pass' => ['Access pass expired', 'A digital studio pass and clock above a coupe, representing an expired session.', '03 / Session control'],
        'queue' => ['Request queue paused', 'A red traffic signal and a queue of requests above a coupe on an inspection platform.', '04 / Flow control'],
        'inspection' => ['System under inspection', 'A precision inspection arm scans a coupe while a diagnostic instrument indicates a system fault.', '05 / System diagnostics'],
        'maintenance' => ['Studio maintenance', 'A studio service gantry and maintenance tool above a parked coupe.', '06 / Studio maintenance'],
        'client' => ['Route needs attention', 'A navigation warning marker above a coupe on a studio grid.', '07 / Request guidance'],
        'system' => ['Service connection interrupted', 'Disconnected diagnostic modules above a coupe, representing a temporary service interruption.', '08 / Service connection'],
    ];
    $variant = is_string($variant ?? null) && isset($illustrations[$variant]) ? $variant : 'system';
    [$artTitle, $artDescription, $artLabel] = $illustrations[$variant];
@endphp
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 560 420"
    role="img"
    aria-labelledby="error-art-title error-art-description"
    data-error-illustration="{{ $variant }}"
    focusable="false"
>
    <title id="error-art-title">{{ $artTitle }}</title>
    <desc id="error-art-description">{{ $artDescription }}</desc>
    <defs>
        <pattern id="error-art-grid" width="28" height="28" patternUnits="userSpaceOnUse">
            <path d="M28 0H0V28" fill="none" stroke="#202022" stroke-width=".7" />
        </pattern>
        <linearGradient id="error-art-body" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#303034" />
            <stop offset="1" stop-color="#141416" />
        </linearGradient>
    </defs>
    <rect width="560" height="420" fill="#0b0b0c" />
    <rect x="28" y="50" width="504" height="312" fill="url(#error-art-grid)" />
    <g fill="none" stroke="#37373b" stroke-width="1">
        <path d="M28 66V50H44M516 50H532V66M28 346V362H44M516 362H532V346" />
        <path d="M56 250V80H504V250M56 108H504M84 80V250M476 80V250" />
        <path d="M28 362H532M56 390H504M84 362L56 390M476 362L504 390" />
    </g>
    <g font-family="monospace" font-size="9" letter-spacing="1.5" fill="#a6a3a0">
        <text x="28" y="29">{{ $artLabel }}</text>
        <text x="461" y="29">DD—STUDIO</text>
        <text x="28" y="408">PRECISION / PROTECTION</text>
        <text x="453" y="408">BAY / 01</text>
    </g>
    <path d="M28 50H84" stroke="#d7232c" stroke-width="2" />
    {{-- Status instruments share one architectural frame and drawing language. --}}
    <g stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        @switch($variant)
            @case('route')
                <rect x="164" y="120" width="232" height="108" rx="3" fill="#101012" stroke="#45454a" />
                <path d="M192 145H247V202H366M220 120V228M304 120V228M164 174H396" fill="none" stroke="#2e2e32" />
                <path d="M192 202H247V148H290" fill="none" stroke="#edeae4" />
                <path d="M298 148H340V176" fill="none" stroke="#d7232c" stroke-dasharray="4 7" />
                <circle cx="192" cy="202" r="5" fill="#edeae4" />
                <circle cx="340" cy="191" r="17" fill="#171012" stroke="#d7232c" />
                <path d="M335 186L345 196M345 186L335 196" stroke="#ee3541" />
                @break
            @case('access')
                <path d="M146 240V118H414V240" fill="#111113" stroke="#45454a" />
                <path d="M159 133H401M159 151H401M159 169H401M159 187H401M159 205H401M159 223H401" stroke="#2c2c30" />
                <rect x="250" y="170" width="60" height="47" rx="4" fill="#1f1013" stroke="#d7232c" />
                <path d="M264 170V158A16 16 0 0 1 296 158V170" fill="#111113" stroke="#edeae4" />
                <circle cx="280" cy="190" r="4" fill="#edeae4" />
                <path d="M280 194V201" stroke="#edeae4" />
                @break
            @case('pass')
                <rect x="178" y="123" width="183" height="100" rx="6" fill="#141416" stroke="#616167" />
                <path d="M178 148H361M201 170H257M201 182H242M201 200H225M231 200H238M244 200H253M259 200H266" stroke="#8e8b86" />
                <path d="M198 135H219" stroke="#d7232c" />
                <circle cx="350" cy="192" r="33" fill="#151013" stroke="#d7232c" />
                <path d="M350 169V192L365 201M350 165V167M375 192H377M350 217V219M323 192H325" fill="none" stroke="#edeae4" />
                @break
            @case('queue')
                <path d="M192 140H262M192 176H262M192 212H262" stroke="#4e4e54" />
                <path d="M204 140H249M204 176H237M204 212H226" stroke="#edeae4" />
                <rect x="294" y="120" width="49" height="110" rx="20" fill="#151517" stroke="#65656b" />
                <circle cx="318" cy="143" r="11" fill="#d7232c" />
                <circle cx="318" cy="175" r="10" fill="#27272b" stroke="#45454a" />
                <circle cx="318" cy="207" r="10" fill="#27272b" stroke="#45454a" />
                <path d="M318 230V245M283 143H268" stroke="#d7232c" />
                @break
            @case('inspection')
                <path d="M145 244V166L207 126L257 165" fill="none" stroke="#929096" stroke-width="4" />
                <circle cx="145" cy="166" r="9" fill="#171719" stroke="#edeae4" />
                <circle cx="207" cy="126" r="9" fill="#171719" stroke="#edeae4" />
                <path d="M245 164L269 181L253 197L232 181Z" fill="#27272c" stroke="#d7232c" />
                <path d="M247 204L226 233M258 205L264 233" stroke="#d7232c" stroke-dasharray="3 6" />
                <rect x="311" y="139" width="91" height="71" rx="3" fill="#141416" stroke="#55555b" />
                <path d="M324 182H337L345 164L357 193L367 176H389" fill="none" stroke="#d7232c" />
                <path d="M356 210V225M339 225H374" stroke="#77747a" />
                @break
            @case('maintenance')
                <path d="M150 244V130H411V244M150 144H411" fill="none" stroke="#5b5b61" stroke-width="3" />
                <path d="M160 130L174 144M184 130L198 144M208 130L222 144M369 130L383 144M393 130L407 144" stroke="#d7232c" />
                <path d="M280 144V165M257 165H303" stroke="#aba7a3" />
                <path d="M292 172A20 20 0 0 1 273 203L253 226L241 214L263 193A20 20 0 0 1 283 166L272 179L279 186Z" fill="#202023" stroke="#edeae4" />
                <path d="M171 239H193M368 239H391" stroke="#d7232c" />
                @break
            @case('client')
                <path d="M174 211H223L241 179H288" fill="none" stroke="#6f6c72" stroke-dasharray="6 6" />
                <path d="M318 126L374 222H263Z" fill="#1d1215" stroke="#d7232c" />
                <path d="M318 158V187" stroke="#edeae4" stroke-width="4" />
                <circle cx="318" cy="203" r="2" fill="#edeae4" />
                <circle cx="174" cy="211" r="5" fill="#edeae4" />
                @break
            @default
                <rect x="164" y="141" width="76" height="74" rx="3" fill="#151517" stroke="#67676d" />
                <rect x="320" y="141" width="76" height="74" rx="3" fill="#151517" stroke="#67676d" />
                <path d="M180 157H222M180 169H211M337 157H379M337 169H369M240 178H263M297 178H320" stroke="#aaa5a2" />
                <path d="M263 167V189M297 167V189M284 157L277 174L287 182L278 201" fill="none" stroke="#d7232c" />
                <circle cx="183" cy="197" r="3" fill="#d7232c" />
                <circle cx="339" cy="197" r="3" fill="#6c6866" />
        @endswitch
    </g>
    {{-- Shared coupe: a low roofline, split glazing, sculpted body and machined wheels. --}}
    <g stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="284" cy="354" rx="204" ry="10" fill="#050505" />
        <path d="M67 341L94 322H465L496 341L468 354H94Z" fill="#171719" stroke="#4a474b" stroke-width="1.5" />
        <path d="M91 344H468M112 349H449" stroke="#d7232c" stroke-width="1.5" />
        <path d="M82 300L103 281L171 274L218 239Q231 230 252 230H316Q339 231 359 249L389 275L455 285Q473 287 480 305L477 324H430Q428 292 401 292Q371 292 369 324H195Q193 292 165 292Q136 292 134 324H90Z" fill="url(#error-art-body)" stroke="#c5c1bd" stroke-width="2" />
        <path d="M192 272L230 244Q235 242 251 242H312Q330 243 344 256L363 274Z" fill="#09090b" stroke="#817e83" stroke-width="1.5" />
        <path d="M275 242L279 273M182 280H378M279 282V312M295 285H310M99 306L130 301M434 297L469 302M208 313H352" fill="none" stroke="#747077" stroke-width="1.5" />
        <path d="M98 288L125 285M448 289L466 294" stroke="#f4f0eb" stroke-width="3" />
        <path d="M96 319H128M203 324H360M437 324H471" stroke="#d7232c" stroke-width="2" />
        <g fill="#101012" stroke="#aaa6a2" stroke-width="2">
            <circle cx="165" cy="321" r="24" />
            <circle cx="401" cy="321" r="24" />
        </g>
        <g fill="none" stroke="#656269" stroke-width="1.5">
            <circle cx="165" cy="321" r="16" />
            <circle cx="401" cy="321" r="16" />
            <path d="M165 305V337M149 321H181M154 310L176 332M154 332L176 310M401 305V337M385 321H417M390 310L412 332M390 332L412 310" />
        </g>
        <g fill="#d7232c">
            <circle cx="165" cy="321" r="4" />
            <circle cx="401" cy="321" r="4" />
        </g>
    </g>
    <g stroke="#66626a" stroke-width="1" fill="none">
        <path d="M90 377H470M90 373V381M470 373V381M45 278V329M41 278H49M41 329H49" />
    </g>
</svg>
