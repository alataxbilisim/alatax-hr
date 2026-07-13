/** Vite client types — shared paketi vite'a bağımlı olmadan ImportMeta.env kullanabilsin. */
interface ImportMetaEnv {
  readonly VITE_API_URL?: string
  readonly VITE_PORTAL_URL?: string
  readonly [key: string]: string | boolean | undefined
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
