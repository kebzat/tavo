@php
    $sent = session('lead_sent');
    $inputClass = 'w-full rounded-2xl border-[1.5px] border-ink/25 bg-cream/60 px-4 py-3.5 text-[15px] text-ink placeholder:text-ink/40 transition focus:border-ink focus:bg-cream focus:outline-none';
    $labelClass = 'mb-2 block text-left text-xs font-bold tracking-[.12em] text-ink/70 uppercase';

    /*
     * Chybu nese text a plný tmavý rámeček, ne barva. Formulář stojí na cihlové
     * (viz cta-band), takže červená ani cihlová by se na něm neztratila jen
     * opticky — splynula by s podkladem.
     */
    $invalidClass = 'border-ink';
    $errorClass = 'mt-1.5 text-[13px] font-bold text-ink';
@endphp

<div class="mx-auto mt-14 w-full max-w-3xl text-left" id="poptavka">
    @if ($sent)
        {{-- role="status" — čtečka potvrzení přečte, i když se na něj uživatel nedívá. --}}
        <div role="status"
             class="rounded-card border-[1.5px] border-ink/20 bg-cream p-8 text-center">
            <div class="text-h3 font-extrabold tracking-[-.02em]">Díky, máme to.</div>
            <p class="mx-auto mt-4 mb-0 max-w-[46ch] text-[16px] leading-[1.6] text-body">
                Ozveme se do dvou pracovních dnů. Pokud to spěchá, napište rovnou na
                <a href="{{ $contact->emailHref() }}" class="font-bold text-brick underline">{{ $contact->email }}</a>.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('lead.store') }}" class="rounded-card bg-cream/35 p-6 md:p-9">
            @csrf

            @if ($errors->any())
                {{--
                    Souhrn chyb je zároveň cíl, na který se po odeslání skočí
                    (`tabindex="-1"` + autofocus), aby uživatel klávesnice i čtečky
                    věděl, proč se formulář neodeslal. Konkrétní chyba je pak
                    ještě u každého pole zvlášť.
                --}}
                <div role="alert"
                     tabindex="-1"
                     autofocus
                     class="mb-6 rounded-2xl border-[1.5px] border-ink bg-cream px-5 py-4 text-[14px] leading-[1.5] text-ink">
                    <strong class="mb-1 block">Formulář se nepodařilo odeslat:</strong>
                    <ul class="m-0 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="lead-name" class="{{ $labelClass }}">Jméno *</label>
                    <input id="lead-name" name="name" type="text" required autocomplete="name"
                           value="{{ old('name') }}"
                           @error('name') aria-invalid="true" aria-describedby="lead-name-error" @enderror
                           class="{{ $inputClass }} @error('name') {{ $invalidClass }} @enderror"
                           placeholder="Jan Novák">
                    @error('name')
                        <p id="lead-name-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="lead-company" class="{{ $labelClass }}">Firma</label>
                    <input id="lead-company" name="company" type="text" autocomplete="organization"
                           value="{{ old('company') }}"
                           @error('company') aria-invalid="true" aria-describedby="lead-company-error" @enderror
                           class="{{ $inputClass }} @error('company') {{ $invalidClass }} @enderror"
                           placeholder="Novák s.r.o.">
                    @error('company')
                        <p id="lead-company-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="lead-email" class="{{ $labelClass }}">E-mail *</label>
                    <input id="lead-email" name="email" type="email" required autocomplete="email"
                           value="{{ old('email') }}"
                           @error('email') aria-invalid="true" aria-describedby="lead-email-error" @enderror
                           class="{{ $inputClass }} @error('email') {{ $invalidClass }} @enderror"
                           placeholder="jan@novak.cz">
                    @error('email')
                        <p id="lead-email-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="lead-phone" class="{{ $labelClass }}">Telefon</label>
                    <input id="lead-phone" name="phone" type="tel" autocomplete="tel"
                           value="{{ old('phone') }}"
                           @error('phone') aria-invalid="true" aria-describedby="lead-phone-error" @enderror
                           class="{{ $inputClass }} @error('phone') {{ $invalidClass }} @enderror"
                           placeholder="+420 777 123 456">
                    @error('phone')
                        <p id="lead-phone-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="lead-topic" class="{{ $labelClass }}">O co jde</label>
                    <select id="lead-topic" name="topic"
                            @error('topic') aria-invalid="true" aria-describedby="lead-topic-error" @enderror
                            class="{{ $inputClass }} @error('topic') {{ $invalidClass }} @enderror">
                        <option value="">Vyberte…</option>
                        @foreach (['Nový web', 'Nový e-shop', 'Předělání webu, který máme', 'Reklama a kampaně', 'Správa a rozvoj webu', 'Něco jiného'] as $topic)
                            <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                        @endforeach
                    </select>
                    @error('topic')
                        <p id="lead-topic-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="lead-budget" class="{{ $labelClass }}">Orientační rozpočet</label>
                    <select id="lead-budget" name="budget"
                            @error('budget') aria-invalid="true" aria-describedby="lead-budget-error" @enderror
                            class="{{ $inputClass }} @error('budget') {{ $invalidClass }} @enderror">
                        <option value="">Zatím nevím</option>
                        @foreach (['do 50 tis. Kč', '50–150 tis. Kč', '150–300 tis. Kč', 'nad 300 tis. Kč', 'měsíční spolupráce'] as $budget)
                            <option value="{{ $budget }}" @selected(old('budget') === $budget)>{{ $budget }}</option>
                        @endforeach
                    </select>
                    @error('budget')
                        <p id="lead-budget-error" class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-5">
                <label for="lead-message" class="{{ $labelClass }}">Napište pár vět o vašem podnikání *</label>
                <textarea id="lead-message" name="message" rows="5" required
                          @error('message') aria-invalid="true" aria-describedby="lead-message-error" @enderror
                          class="{{ $inputClass }} @error('message') {{ $invalidClass }} @enderror"
                          placeholder="Co děláte, kam se chcete dostat a co vás teď nejvíc brzdí.">{{ old('message') }}</textarea>
                @error('message')
                    <p id="lead-message-error" class="{{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>

            {{-- Honeypot — pro lidi neviditelné, roboti ho vyplní a poptávku odmítneme. --}}
            <div aria-hidden="true" class="absolute h-0 w-0 overflow-hidden opacity-0">
                <label for="lead-website">Web (nevyplňujte)</label>
                <input id="lead-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <label class="mt-5 flex items-start gap-3 text-left text-[13px] leading-[1.5] text-ink/80">
                <input name="gdpr" type="checkbox" value="1" required
                       @error('gdpr') aria-invalid="true" aria-describedby="lead-gdpr-error" @enderror
                       class="mt-0.5 h-4 w-4 accent-ink">
                <span>
                    Souhlasím se zpracováním údajů za účelem odpovědi na poptávku.
                    <a href="{{ url('/ochrana-osobnich-udaju') }}" class="font-bold underline">Více informací</a>.
                </span>
            </label>
            @error('gdpr')
                <p id="lead-gdpr-error" class="{{ $errorClass }}">{{ $message }}</p>
            @enderror

            <div class="mt-7 flex flex-wrap items-center gap-4">
                <x-btn type="submit" variant="dark" size="lg">Odeslat poptávku</x-btn>
                <span class="text-[13px] text-ink/65">Ozveme se do dvou pracovních dnů.</span>
            </div>
        </form>
    @endif
</div>
