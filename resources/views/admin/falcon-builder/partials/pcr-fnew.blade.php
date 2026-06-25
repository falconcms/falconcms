{{-- Shared FalconCMS color-picker (Pickr) skin — used everywhere via .pcr-fnew. Raw CSS only (no <style> wrapper). --}}
    .pcr-app.pcr-fnew {
        width: auto !important;
        max-width: 268px !important;
        padding: 10px !important;
        border-radius: 12px !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: 0 12px 32px rgba(0,0,0,0.18) !important;
    }
    .pcr-app.pcr-fnew .pcr-selection {
        flex-direction: row !important;
        height: 170px !important;
        margin-bottom: 10px !important;
        gap: 10px !important;
    }
    .pcr-app.pcr-fnew .pcr-selection .pcr-color-preview { display: none !important; }
    .pcr-app.pcr-fnew .pcr-selection .pcr-color-palette {
        width: 170px !important;
        height: 170px !important;
        margin-right: 0 !important;
        border-radius: 10px !important;
        overflow: hidden !important;
    }
    .pcr-app.pcr-fnew .pcr-selection .pcr-color-palette .pcr-palette { border-radius: 10px !important; }
    .pcr-app.pcr-fnew .pcr-selection .pcr-picker {
        width: 18px !important;
        height: 18px !important;
        border: 2px solid #fff !important;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.25), 0 1px 4px rgba(0,0,0,0.45) !important;
    }
    .pcr-app.pcr-fnew .pcr-selection .pcr-sliders {
        flex-direction: row !important;
        gap: 12px !important;
        flex: 0 0 auto !important;
    }
    .pcr-app.pcr-fnew .pcr-selection .pcr-hue,
    .pcr-app.pcr-fnew .pcr-selection .pcr-opacity {
        width: 18px !important;
        height: 190px !important;
        border-radius: 10px !important;
        overflow: hidden !important;
    }
    .pcr-app.pcr-fnew .pcr-selection .pcr-hue .pcr-picker,
    .pcr-app.pcr-fnew .pcr-selection .pcr-opacity .pcr-picker {
        width: 24px !important;
        height: 10px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        border-radius: 5px !important;
        border: 2px solid #fff !important;
        box-shadow: 0 1px 4px rgba(0,0,0,0.45) !important;
    }
    .pcr-app.pcr-fnew .pcr-interaction {
        gap: 8px !important;
        padding-top: 10px !important;
        border-top: 1px solid #f1f1f1 !important;
    }
    .pcr-app.pcr-fnew .pcr-interaction input.pcr-result {
        border-radius: 8px !important;
        height: 30px !important;
        border: 1px solid #e5e7eb !important;
    }
    /* Opacity slider tinted with the picked colour (default Pickr gradient is fixed black) */
    .pcr-app.pcr-fnew .pcr-color-opacity .pcr-slider {
        background: linear-gradient(to bottom, transparent, var(--fnew-opcolor, #000)),
            url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 2 2'><path fill='white' d='M1,0H2V1H1V0ZM0,1H1V2H0V1Z'/><path fill='gray' d='M0,0H1V1H0V0ZM1,1H2V2H1V1Z'/></svg>") !important;
        background-size: 100%, 50% !important;
    }
    /* Minimal: hide swatches + bottom button row (square + sliders only) */
    .pcr-app.pcr-fnew .pcr-swatches { display: none !important; }
    .pcr-app.pcr-fnew .pcr-interaction { display: none !important; }
