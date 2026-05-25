<script setup lang="ts">
defineProps<{
  title: string
  subtitle: string
}>()
</script>

<template>
  <div>
    <PageHeader
      :title="title"
      :subtitle="subtitle"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: title }]"
    />

    <Card class="border-0 p-6 shadow">
      <p class="text-sm leading-7 text-muted-foreground">
        تم إنشاء هذه الصفحة داخل مشروع Nuxt الجديد كجزء من استنساخ الواجهة 1:1،
        وسيتم الآن نقل تفاصيلها وسلوكها من نسخة React مع الحفاظ على نفس التجربة.
      </p>
    </Card>
  </div>
</template>
