@php
    $logoPath = public_path('images/cperas-logo.webp');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp

<style>
    .pdf-header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }

    .pdf-header-table td {
        border: none;
        padding: 0;
        vertical-align: middle;
    }

    .pdf-logo-cell {
        width: 70px;
    }

    .pdf-logo {
        width: 58px;
        height: 58px;
        text-align: center;
        overflow: hidden;
    }

    .pdf-logo-img {
        width: 58px;
        height: 58px;
        display: block;
    }

    .pdf-logo-fallback {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: #800000;
        color: #ffcc00;
        text-align: center;
        line-height: 58px;
        font-size: 16px;
        font-weight: 700;
    }

    .pdf-header-small {
        font-size: 10px;
        font-weight: 700;
        color: #374151;
        line-height: 1.25;
    }

    .pdf-header-main {
        margin-top: 2px;
        font-size: 15px;
        font-weight: 700;
        color: #4b0000;
        line-height: 1.2;
    }

    .pdf-header-line {
        height: 3px;
        margin-top: 8px;
        background: #ffcc00;
        border-bottom: 2px solid #800000;
    }
</style>

<table class="pdf-header-table">
    <tr>
        <td class="pdf-logo-cell">
            <div class="pdf-logo">
                @if ($logoSrc)
                    <img class="pdf-logo-img" src="{{ $logoSrc }}" alt="CPE Room Availability System Logo">
                @else
                    <div class="pdf-logo-fallback"></div>
                @endif
            </div>
        </td>
        <td>
            <div class="pdf-header-small">POLYTECHNIC UNIVERSITY OF THE PHILIPPINES</div>
            <div class="pdf-header-small">COLLEGE OF ENGINEERING</div>
            <div class="pdf-header-main">COMPUTER ENGINEERING DEPARTMENT</div>
        </td>
    </tr>
</table>
<div class="pdf-header-line"></div>
