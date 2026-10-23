import { expect, test } from 'claude-code/testing'

const PANEL = {
  plugin: 'co-agent-dela',
  component: 'Pane',
  requestId: 'co-agent-dela',
  props: {
    title: 'Co agent dělá',
    isFocused: false,
    bodyColumns: 60,
    placement: 'dock',
    scroll: { offset: 0, bodyRows: 30 },
    view: {},
  },
} as const

const TED = Date.UTC(2026, 9, 23, 8, 0, 0)

// Pod modem nic neběží: tyhle hooky hrají engine.
function engine(on: Parameters<Parameters<typeof test>[1]>[1], odpoved: (e: { tool: string }) => object) {
  on('command.register', () => ({ value: { command: 'co-agent-dela' } }))
  on('ui.open', () => ({ value: { isPlaced: true } }))
  on('session.start', (_, e) => ({ cwd: e.cwd }))
  on('clock.now', () => ({ value: TED }))
  on('tool.call', (_, e) => odpoved(e))
}

test('panel ukáže kroky česky a stav každého', async ($, on) => {
  engine(on, e => (e.tool === 'Bash' ? { result: {}, isError: true, text: 'chyba' } : { result: {}, text: 'ok' }))

  await $.session.start({ cwd: '/projekty/taveo/kod', surface: 'desktop', isInteractive: true })
  await $.tool.call({ tool: 'Read', file_path: '/projekty/taveo/kod/routes/web.php' })
  await $.tool.call({ tool: 'Bash', command: 'php artisan test', description: 'Spustí testy' })

  const ui = await $.ui.mount({ ...PANEL, surface: 'desktop' })

  expect((await ui.find({ type: 'Text', text: /Čte routes\/web\.php/ }))?.text).toMatch(/^✓/)
  expect((await ui.find({ type: 'Text', text: /Příkaz: Spustí testy/ }))?.text).toMatch(/^✗/)
  await ui.unmount()
})

test('po prohlížeči ukáže adresu, snímek a pruh s tlačítkem Kouknout', async ($, on) => {
  engine(on, e =>
    e.tool === 'mcp__prohlizec__browser_take_screenshot'
      ? { result: {}, text: '### Result\n- [Screenshot of viewport](./mobil.png)' }
      : { result: {}, text: 'ok' },
  )

  await $.session.start({ cwd: '/projekty/taveo/kod', surface: 'desktop', isInteractive: true })
  await $.tool.call({ tool: 'mcp__prohlizec__browser_navigate', url: 'https://taveo.cz/nastroje' })
  await $.tool.call({ tool: 'mcp__prohlizec__browser_take_screenshot', filename: 'mobil.png' })

  const panel = await $.ui.mount({ ...PANEL, surface: 'desktop' })
  expect(await panel.find({ type: 'Text', text: /Prohlížeč: otevírá https:\/\/taveo\.cz\/nastroje/ })).toBeTruthy()
  expect(await panel.find({ type: 'Text', text: /\/projekty\/taveo\/kod\/mobil\.png/ })).toBeTruthy()
  await panel.unmount()

  // V terminálu je snímek obrázek (kde to terminál neumí, ukáže popisek).
  const terminal = await $.ui.mount({ ...PANEL, surface: 'terminal' })
  expect(await terminal.find({ type: 'Image', key: 'snimek' })).toBeTruthy()
  await terminal.unmount()

  const pruh = await $.ui.mount({
    plugin: 'co-agent-dela',
    component: 'AbovePrompt',
    surface: 'terminal',
    props: { hasSurvey: false },
  })
  expect(await pruh.find({ type: 'Button', key: 'kouknout' })).toBeTruthy()
  await pruh.unmount()
})
