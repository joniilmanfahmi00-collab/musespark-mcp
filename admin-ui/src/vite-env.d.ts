/// <reference types="vite/client" />

interface Window {
  MusesparkMCP?: {
    restUrl: string
    nonce: string
    adminUrl: string
    assetBaseUrl: string
  }
}

interface ImportMetaEnv {
  readonly BASE_URL: string
  readonly DEV: boolean
  readonly PROD: boolean
  readonly MODE: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}