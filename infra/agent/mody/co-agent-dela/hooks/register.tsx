import { atom, read, update } from 'claude-code'
import type { Register } from 'claude-code'

import type { Krok, Prohlizec } from '../types'

// Panel „Co agent dělá“: každé volání nástroje jako srozumitelný krok
// a poslední snímek z prohlížeče. Když agent sáhne po prohlížeči, nad
// vstupním polem se ukáže pruh s tlačítkem Kouknout.

const PANEL = 'co-agent-dela'
const TITULEK = 'Co agent dělá'
const PROHLIZEC_MCP = 'mcp__prohlizec__'
const PRUH_VTERIN = 120

const kroky = atom({ plugin: 'co-agent-dela', key: 'kroky' } as const, [], {
  shape: 'kroky-1',
})
const prohlizec = atom(
  { plugin: 'co-agent-dela', key: 'prohlizec' } as const,
  { url: null, snimek: null, snimekCas: 0, posledniCas: 0 },
  { shape: 'prohlizec-1' },
)
const pruhSkryty = atom({ plugin: 'co-agent-dela', key: 'pruhSkryty' } as const, false)

type Args = Record<string, unknown>

const text = (value: unknown): string => (typeof value === 'string' ? value : '')

const zkrat = (value: string, max = 70): string =>
  value.length > max ? `${value.slice(0, max - 1)}…` : value

const soubor = (path: string): string => {
  const parts = path.split('/').filter(Boolean)

  return parts.slice(-2).join('/') || path
}

/** Lidský popis volání nástroje. */
function popis(tool: string, a: Args): Pick<Krok, 'popis' | 'druh'> {
  if (tool.startsWith(PROHLIZEC_MCP)) {
    const akce = tool.slice(PROHLIZEC_MCP.length)
    const co: Record<string, string> = {
      browser_navigate: `otevírá ${text(a.url)}`,
      browser_navigate_back: 'jde zpět',
      browser_take_screenshot: 'fotí obrazovku',
      browser_snapshot: 'čte obsah stránky',
      browser_resize: `mění velikost na ${String(a.width)}×${String(a.height)}`,
      browser_click: `kliká na ${text(a.element) || 'prvek'}`,
      browser_type: `píše do ${text(a.element) || 'pole'}`,
      browser_fill_form: 'vyplňuje formulář',
      browser_console_messages: 'kontroluje chyby v konzoli',
      browser_network_requests: 'kontroluje síťové požadavky',
      browser_wait_for: 'čeká na stránku',
      browser_close: 'zavírá prohlížeč',
    }

    return { popis: `Prohlížeč: ${co[akce] ?? akce.replace('browser_', '')}`, druh: 'prohlizec' }
  }

  switch (tool) {
    case 'Read':
      return { popis: `Čte ${soubor(text(a.file_path))}`, druh: 'soubor' }
    case 'Edit':
      return { popis: `Upravuje ${soubor(text(a.file_path))}`, druh: 'soubor' }
    case 'Write':
      return { popis: `Zapisuje ${soubor(text(a.file_path))}`, druh: 'soubor' }
    case 'Bash':
      return { popis: `Příkaz: ${text(a.description) || zkrat(text(a.command), 60)}`, druh: 'prikaz' }
    case 'Grep':
    case 'Glob':
      return { popis: `Hledá v kódu: ${zkrat(text(a.pattern), 50)}`, druh: 'hledani' }
    case 'Agent':
      return { popis: `Pomocný agent: ${text(a.description)}`, druh: 'jine' }
    case 'WebFetch':
    case 'WebSearch':
      return { popis: `Web: ${zkrat(text(a.url) || text(a.query), 60)}`, druh: 'hledani' }
    case 'AskUserQuestion':
      return { popis: 'Ptá se tě na rozhodnutí', druh: 'jine' }
    default:
      return { popis: tool, druh: 'jine' }
  }
}

/** Cesta ke snímku z odpovědi prohlížeče: `[Screenshot …](./soubor.png)`. */
function snimekZOdpovedi(odpoved: string, koren: string): string | null {
  const cesta = /\]\(([^)\s]+\.(?:png|jpe?g))\)/i.exec(odpoved)?.[1]

  if (!cesta) {
    return null
  }

  return cesta.startsWith('/') ? cesta : `${koren}/${cesta.replace(/^\.\//, '')}`
}

const hodiny = (ms: number): string => {
  const d = new Date(ms)
  const dve = (n: number) => String(n).padStart(2, '0')

  return `${dve(d.getHours())}:${dve(d.getMinutes())}:${dve(d.getSeconds())}`
}

const otevri = ($: { ui: { open: (args: { id: string; title: string }) => Promise<unknown> } }) =>
  $.ui.open({ id: PANEL, title: TITULEK })

export const register: Register = on => {
  // Složka konverzace: relativní snímky prohlížeče leží v ní. Načítá se
  // i po reloadu modu, ten pouští session.start znovu.
  let koren = ''

  on('session.start', async ($, e, next) => {
    koren = e.cwd
    await $.command.register({
      name: 'co-agent-dela',
      description: 'Otevře panel s tím, co agent právě dělá',
    })

    return next(e)
  })

  on('command.run', { command: 'co-agent-dela' }, async $ => {
    await otevri($)

    return { text: 'Panel „Co agent dělá“ je otevřený.' }
  })

  // Evidence kroků nesmí nikdy zastavit práci agenta: chyba panelu se
  // spolkne a nástroj běží dál, jako by mod nebyl.
  on('tool.call', async ($, e, next) => {
    const args = e as unknown as Args
    let krok: Krok | null = null

    try {
      const zacatek = await $.clock.now()
      const { popis: lidsky, druh } = popis(e.tool, args)
      krok = {
        id: e.tool_use_id ?? `${e.tool}-${zacatek}`,
        popis: e.agentId ? `(pomocník) ${lidsky}` : lidsky,
        druh,
        stav: 'bezi',
        cas: zacatek,
      }
      const novy = krok
      await update($, kroky, list => [...list, novy].slice(-200))

      if (druh === 'prohlizec') {
        const url = e.tool === `${PROHLIZEC_MCP}browser_navigate` ? text(args.url) : null
        await update($, prohlizec, p => ({ ...p, url: url ?? p.url, posledniCas: zacatek }))
        await update($, pruhSkryty, () => false)
      }
    } catch {
      krok = null
    }

    const vysledek = await next(e)

    if (krok === null) {
      return vysledek
    }

    try {
      const hotovy = krok
      const chyba = 'deny' in vysledek && vysledek.deny !== undefined ? true : Boolean(vysledek.isError)

      await update($, kroky, list =>
        list.map(one => (one.id === hotovy.id ? { ...one, stav: chyba ? 'chyba' : 'hotovo' } : one)),
      )

      if (e.tool === `${PROHLIZEC_MCP}browser_take_screenshot` && !chyba) {
        const jmeno = text(args.filename)
        const cesta =
          snimekZOdpovedi(vysledek.text ?? '', koren) ??
          (jmeno ? (jmeno.startsWith('/') ? jmeno : `${koren}/${jmeno.replace(/^\.\//, '')}`) : null)

        if (cesta) {
          const ted = await $.clock.now()
          await update($, prohlizec, p => ({ ...p, snimek: cesta, snimekCas: ted }))
        }
      }
    } catch {
      // Panel jen nedostane konec kroku.
    }

    return vysledek
  })

  // Pruh nad vstupním polem: dvě minuty po posledním kroku v prohlížeči.
  on('ui.render', { component: 'AbovePrompt' }, async ($, e, next) => {
    const p = await read($, prohlizec)
    const ted = await $.clock.now()
    const cerstve = p.posledniCas > 0 && ted - p.posledniCas < PRUH_VTERIN * 1000

    if (e.props.hasSurvey || !cerstve || (await read($, pruhSkryty))) {
      return next(e)
    }

    const { Box, Button, Text } = $.ui.resolve(e)

    return (
      <Box>
        <Text>Agent kontroluje web v prohlížeči{p.url ? `: ${zkrat(p.url, 50)}` : ''} </Text>
        <Button key="kouknout" label="Kouknout" onPress={() => otevri($)} />
        <Text> </Text>
        <Button key="skryt" label="Skrýt" onPress={() => update($, pruhSkryty, () => true)} />
      </Box>
    )
  })

  on('ui.render', { component: 'Pane', requestId: PANEL }, async ($, e) => {
    const prvky = $.ui.resolve(e)
    const { Box, Text } = prvky
    // Obrázek umí jen terminál (a i ten jen v kitty, Ghostty, iTerm2), jinde cesta.
    const Image = e.surface === 'terminal' && 'Image' in prvky ? prvky.Image : undefined
    const list = await read($, kroky)
    const p = await read($, prohlizec)
    const sirka = Math.max(20, Math.min(80, (e.viewport?.columns ?? 60) - 2))
    const vyskaObrazku = p.snimek ? Math.max(6, Math.round(sirka * 0.33)) : 0
    const mista = Math.max(3, (e.viewport?.rows ?? 30) - vyskaObrazku - 7)
    const bezi = [...list].reverse().find(k => k.stav === 'bezi')

    return (
      <Box flexDirection="column">
        <Text bold>Teď: {bezi ? bezi.popis : list.length ? 'čeká na tebe nebo přemýšlí' : 'zatím nic'}</Text>
        <Text> </Text>
        {list.length === 0 && <Text dimColor>Zatím žádné kroky.</Text>}
        {list.slice(-mista).map(k => (
          <Text key={k.id} dimColor={k.stav === 'hotovo'} color={k.stav === 'chyba' ? 'red' : undefined}>
            {k.stav === 'bezi' ? '▶' : k.stav === 'chyba' ? '✗' : '✓'} {hodiny(k.cas)} {zkrat(k.popis, sirka - 12)}
          </Text>
        ))}
        {(p.url || p.snimek) && (
          <Box flexDirection="column" marginTop={1}>
            <Text bold>Prohlížeč{p.url ? `: ${zkrat(p.url, sirka - 12)}` : ''}</Text>
            {p.snimek && Image && (
              <Image
                key="snimek"
                source={{ file: p.snimek, format: 'png', generation: p.snimekCas }}
                columns={sirka}
                rows={vyskaObrazku}
                alt={`Poslední snímek (${hodiny(p.snimekCas)}): ${p.snimek}`}
              />
            )}
            {p.snimek && !Image && <Text dimColor>Poslední snímek ({hodiny(p.snimekCas)}): {p.snimek}</Text>}
          </Box>
        )}
      </Box>
    )
  })
}
