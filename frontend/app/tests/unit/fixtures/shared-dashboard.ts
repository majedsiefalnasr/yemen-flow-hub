export const metricFixtures = {
  positive: {
    label: 'الطلبات المعتمدة',
    value: '١٢٠',
    trend: { direction: 'up' as const, value: '+١٢%' },
    tone: 'success' as const,
  },
  negative: {
    label: 'الحالات المتأخرة',
    value: '٩',
    trend: { direction: 'down' as const, value: '-٣%' },
    tone: 'danger' as const,
  },
  neutral: {
    label: 'الطلبات قيد المراجعة',
    value: '٤٥',
    trend: { direction: 'neutral' as const, value: '٠%' },
    tone: 'info' as const,
  },
}

export const analyticsStateFixtures = {
  loading: { state: 'loading' as const },
  empty: { state: 'empty' as const, emptyText: 'لا توجد عناصر لعرضها' },
  error: { state: 'error' as const, errorText: 'حدث خطأ أثناء التحميل' },
}
