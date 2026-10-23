/** Jeden krok agenta: volání nástroje popsané lidsky. */
export type Krok = {
  id: string
  popis: string
  druh: 'soubor' | 'prikaz' | 'prohlizec' | 'hledani' | 'jine'
  stav: 'bezi' | 'hotovo' | 'chyba'
  cas: number
}

/** Co agent naposledy dělal v prohlížeči. */
export type Prohlizec = {
  url: string | null
  snimek: string | null
  snimekCas: number
  posledniCas: number
}

declare module 'claude-code' {
  interface PluginState {
    'co-agent-dela': {
      kroky: Shaped<Krok[]>
      prohlizec: Shaped<Prohlizec>
      pruhSkryty: boolean
    }
  }
}
