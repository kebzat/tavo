{{--
    Lokální stránka pro zkopírování podpisu. Styly stránky jsou inline v <style>,
    protože nejde o web: nepotřebuje Vite ani Tailwind a má fungovat i bez buildu.
    Podpis se kopíruje tlačítkem přes Clipboard API přesně tak, jak je napsaný.
    Ruční označení myší by Chrome doplnil o vypočtené styly stránky.
--}}
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>E-mailový podpis</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 40px 16px 64px; background: #f4ede1; color: #131110; font: 15px/1.5 Montserrat, Arial, sans-serif; }
        main { max-width: 760px; margin: 0 auto; }
        h1 { margin: 0 0 6px; font-size: 28px; line-height: 1.1; }
        h2 { margin: 36px 0 10px; font-size: 17px; }
        p { margin: 0 0 10px; color: #3a362e; }
        .note { margin: 18px 0 0; padding: 12px 16px; border-radius: 10px; background: #fff3cd; color: #5c4a00; font-size: 14px; }
        .previews { display: grid; gap: 14px; margin-top: 24px; }
        .preview { border-radius: 14px; padding: 28px 24px; overflow-x: auto; }
        .preview--light { background: #ffffff; }
        .preview--dark { background: #1f1f1f; }
        .preview__label { margin: 0 0 16px; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: #857f75; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 18px; }
        button { font: inherit; font-weight: 600; border: 0; border-radius: 999px; padding: 12px 22px; cursor: pointer; background: #db4b24; color: #fff; }
        button:hover { background: #c23e1a; }
        button.secondary { background: #131110; }
        button.secondary:hover { background: #2a2622; }
        .status { font-size: 14px; color: #4f6b3a; font-weight: 600; }
        textarea { width: 100%; min-height: 160px; margin-top: 10px; padding: 12px; border: 1px solid #cdc4b0; border-radius: 10px; font: 12px/1.45 ui-monospace, Menlo, monospace; background: #fff; color: #131110; }
        ol { margin: 0; padding-left: 20px; color: #3a362e; }
        li { margin-bottom: 6px; }
        code { font-size: 13px; background: #fff; padding: 1px 5px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1>E-mailový podpis</h1>
    <p>Pro schránku {{ $contact->email }}. Údaje se berou z administrace (Kontakt a Zakladatelé).</p>

    @if ($usesLocalImages)
        <p class="note">Obrázky se teď načítají z localhostu, jen pro kontrolu vzhledu. Tuhle verzi do klienta nekopíruj, příjemci by obrázky neviděli.</p>
    @else
        <p class="note">Obrázky se načítají z <strong>taveo.cz/images/email/</strong>. Když se nezobrazují, složka ještě není nasazená na ostrý web. Nejdřív nasaď, pak kopíruj.</p>
    @endif

    <div class="previews">
        <div class="preview preview--light">
            <p class="preview__label">Světlý režim</p>
            <div id="signature">@include('email-signature.signature')</div>
        </div>
        <div class="preview preview--dark">
            <p class="preview__label">Tmavý režim</p>
            @include('email-signature.signature')
        </div>
    </div>

    <div class="actions">
        <button type="button" data-copy="html">Zkopírovat podpis</button>
        <button type="button" class="secondary" data-copy="source">Zkopírovat HTML kód</button>
        <span class="status" data-status role="status"></span>
    </div>

    <h2>Kam ho vložit</h2>
    <ol>
        <li><strong>Gmail:</strong> Nastavení → Zobrazit všechna nastavení → Obecné → Podpis → Vytvořit nový → vložit (Cmd+V) → dole Uložit změny.</li>
        <li><strong>Apple Mail:</strong> Mail → Nastavení → Podpisy → + → vložit. Odškrtni „Vždy použít výchozí písmo zprávy“, jinak Mail přepíše písmo i barvy.</li>
        <li><strong>Outlook:</strong> Nastavení → Pošta → Podpisy (v aplikaci Soubor → Možnosti → Pošta → Podpisy) → Nový → vložit.</li>
        <li><strong>Thunderbird:</strong> Nastavení účtu → „Použít HTML“ → vlož kód z tlačítka Zkopírovat HTML kód.</li>
        <li><strong>CyberPanel / Roundcube / SnappyMail:</strong> Nastavení → Identity → Podpis → přepnout na HTML → vložit.</li>
    </ol>

    <h2>HTML kód</h2>
    <textarea readonly data-source aria-label="HTML kód podpisu"></textarea>
</main>

<script>
    const signature = document.getElementById('signature');
    const html = signature.innerHTML.replace(/>\s+</g, '><').trim();
    const source = document.querySelector('[data-source]');
    const status = document.querySelector('[data-status]');
    source.value = html;

    function flash(message) {
        status.textContent = message;
        clearTimeout(flash.timer);
        flash.timer = setTimeout(() => (status.textContent = ''), 3000);
    }

    // Záloha pro prohlížeče bez ClipboardItem: označí podpis a zkopíruje výběr.
    function copyBySelection(node) {
        const range = document.createRange();
        range.selectNodeContents(node);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        document.execCommand('copy');
        selection.removeAllRanges();
    }

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                if (button.dataset.copy === 'source') {
                    await navigator.clipboard.writeText(html);
                    flash('HTML kód zkopírován.');
                    return;
                }

                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/html': new Blob([html], { type: 'text/html' }),
                        'text/plain': new Blob([signature.innerText], { type: 'text/plain' }),
                    }),
                ]);
                flash('Podpis zkopírován, vlož ho do klienta.');
            } catch (error) {
                copyBySelection(button.dataset.copy === 'source' ? source : signature);
                flash('Zkopírováno.');
            }
        });
    });
</script>
</body>
</html>
