<script setup lang="ts">
import { ref, computed } from 'vue'
import DataTableWithTabs from './DataTableWithTabs.vue'

interface TableData {
  id: number
  header: string
  type: string
  status: string
  target: string
  limit: string
  reviewer: string
}

// Sample data
const tableData = ref<TableData[]>([
  {
    id: 1,
    header: 'Q1 Performance Review',
    type: 'Section',
    status: 'Done',
    target: '100%',
    limit: '50',
    reviewer: 'Alice Johnson',
  },
  {
    id: 2,
    header: 'Budget Allocation',
    type: 'Finance',
    status: 'In Progress',
    target: '80%',
    limit: '30',
    reviewer: 'Assign reviewer',
  },
  {
    id: 3,
    header: 'System Maintenance',
    type: 'Infrastructure',
    status: 'Done',
    target: '100%',
    limit: '20',
    reviewer: 'Bob Smith',
  },
  {
    id: 4,
    header: 'Data Migration',
    type: 'Technical',
    status: 'In Progress',
    target: '60%',
    limit: '100',
    reviewer: 'Assign reviewer',
  },
  {
    id: 5,
    header: 'Security Audit',
    type: 'Compliance',
    status: 'Done',
    target: '100%',
    limit: '75',
    reviewer: 'Carol Williams',
  },
])
</script>

<template>
  <div class="bg-background min-h-screen p-4 lg:p-6">
    <div class="mx-auto max-w-7xl">
      <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight">Data Table with Tabs</h1>
        <p class="text-muted-foreground">
          Sort, filter, paginate, and toggle column visibility across multiple tabs.
        </p>
      </div>

      <!-- Component usage -->
      <DataTableWithTabs :data="tableData" />
    </div>
  </div>
</template>
