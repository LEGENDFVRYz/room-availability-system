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
        position: relative;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: #800000;
        text-align: center;
        overflow: hidden;
    }

    .pdf-logo-mark {
        position: absolute;
        left: 13px;
        top: 11px;
        width: 32px;
        height: 34px;
        display: block;
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
                <svg class="pdf-logo-mark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" aria-hidden="true">
                    <path
                        transform="translate(0, 1) scale(1.6667)"
                        fill="#ffcc00"
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
                    />
                </svg>
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
