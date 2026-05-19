import { useColorScheme } from '../composables/useColorScheme'

export default defineNuxtPlugin(() => {
  const { hydrate } = useColorScheme()
  hydrate()
})
