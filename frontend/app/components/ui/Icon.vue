<script setup lang="ts">
import { computed } from 'vue'
import {
  Home,
  FileText,
  PlusCircle,
  Building,
  Building2,
  Stamp,
  BarChart2,
  ShieldCheck,
  Bell,
  Landmark,
  Users,
  User,
  FileCog,
  UserCheck,
  Settings,
  Menu,
  X,
  Search,
  Sliders,
  Sun,
  Moon,
  ChevronDown,
  Clock,
  CheckCircle,
  XCircle,
  RotateCcw,
  UploadCloud,
  Vote,
} from 'lucide-vue-next'

const ICONS = {
  home: Home,
  'file-text': FileText,
  'plus-circle': PlusCircle,
  building: Building,
  bank: Building2,
  stamp: Stamp,
  'bar-chart-2': BarChart2,
  'shield-check': ShieldCheck,
  bell: Bell,
  landmark: Landmark,
  users: Users,
  user: User,
  'file-cog': FileCog,
  'user-check': UserCheck,
  settings: Settings,
  menu: Menu,
  x: X,
  search: Search,
  sliders: Sliders,
  sun: Sun,
  moon: Moon,
  'chevron-down': ChevronDown,
  clock: Clock,
  'check-circle': CheckCircle,
  'x-circle': XCircle,
  'rotate-ccw': RotateCcw,
  'upload-cloud': UploadCloud,
  vote: Vote,
} as const

type IconName = keyof typeof ICONS

const props = withDefaults(defineProps<{
  name: IconName
  size?: number
}>(), {
  size: 18,
})

const component = computed(() => ICONS[props.name])
</script>

<template>
  <component :is="component" :size="size" :aria-hidden="true" />
</template>
