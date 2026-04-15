export interface ModelDefinition {
  code: string
  providerName: string
  displayName: string
  label: string
  openRouterModelId: string
  contextWindow: number
  order: number
}

export interface AppConfig {
  models: ModelDefinition[]
  apiKeyRequired: boolean
  layout: {
    desktopColumns: number
  }
}
