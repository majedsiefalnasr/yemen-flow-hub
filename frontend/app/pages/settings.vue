<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import { Bell, Mail, Settings as Cog, ShieldAlert, Workflow } from 'lucide-vue-next'
import { useTheme } from '@/composables/useTheme'

const { isDark, toggleTheme } = useTheme()

const workflowSettings = reactive({
  supportMembers: '5',
  executiveMembers: '6',
  quorum: '4',
  reviewHours: '48',
  hiddenVoting: true,
  managerWeight: true,
})

const emailSettings = reactive({
  host: 'smtp.cby.gov.ye',
  port: '587',
  username: 'noreply@cby.gov.ye',
  password: '************',
  approvalTemplate: 'عزيزي {{importer}}،\nنخبركم باعتماد طلب التمويل رقم {{ref}} بمبلغ {{amount}} {{currency}}.',
})

const notificationSettings = reactive([
  { label: 'البريد الإلكتروني عند تقديم طلب جديد', enabled: true },
  { label: 'إشعار داخل المنصة عند تغيير حالة طلب', enabled: true },
  { label: 'SMS عند اعتماد/رفض الطلب', enabled: true },
  { label: 'تنبيه فوري عند اكتشاف فاتورة مكررة', enabled: true },
  { label: 'تقرير يومي بإجمالي النشاط', enabled: true },
])

const securitySettings = reactive([
  { label: 'إلزام المصادقة الثنائية MFA', enabled: true },
  { label: 'انتهاء كلمة المرور كل 90 يوم', enabled: true },
  { label: 'قفل الحساب بعد 5 محاولات فاشلة', enabled: true },
  { label: 'تشفير الوثائق المرفوعة AES-256', enabled: true },
  { label: 'تسجيل كل عملية في سجل التدقيق', enabled: true },
  { label: 'السماح بالوصول من خارج الشبكة', enabled: false },
])

const generalSettings = reactive({
  platformName: 'منصة إدارة وتمويل الواردات',
  authority: 'البنك المركزي اليمني',
  timeZone: 'GMT+3 (Arabia)',
})
</script>

<template>
  <div>
    <PageHeader
      title="إعدادات النظام"
      subtitle="إدارة سير العمل، الإشعارات، والأمن"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الإعدادات' }]"
    />

    <Tabs default-value="workflow">
      <TabsList>
        <TabsTrigger value="workflow">
          <Workflow class="ms-1 h-4 w-4" />
          سير العمل
        </TabsTrigger>
        <TabsTrigger value="email">
          <Mail class="ms-1 h-4 w-4" />
          البريد
        </TabsTrigger>
        <TabsTrigger value="notif">
          <Bell class="ms-1 h-4 w-4" />
          الإشعارات
        </TabsTrigger>
        <TabsTrigger value="security">
          <ShieldAlert class="ms-1 h-4 w-4" />
          الأمن
        </TabsTrigger>
        <TabsTrigger value="general">
          <Cog class="ms-1 h-4 w-4" />
          عام
        </TabsTrigger>
      </TabsList>

      <TabsContent
        value="workflow"
        class="mt-4"
      >
        <Card class="space-y-5 border-0 p-6 shadow-card">
          <h3 class="font-semibold">
            إعدادات دورة الموافقة
          </h3>
          <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
              <Label>عدد أعضاء اللجنة المساندة</Label>
              <Input v-model="workflowSettings.supportMembers" />
            </div>
            <div class="space-y-2">
              <Label>عدد أعضاء اللجنة التنفيذية</Label>
              <Input v-model="workflowSettings.executiveMembers" />
            </div>
            <div class="space-y-2">
              <Label>الحد الأدنى للنصاب</Label>
              <Input v-model="workflowSettings.quorum" />
            </div>
            <div class="space-y-2">
              <Label>مهلة المراجعة (ساعات)</Label>
              <Input v-model="workflowSettings.reviewHours" />
            </div>
          </div>
          <div class="flex items-center justify-between rounded-lg border p-3">
            <div>
              <div class="text-sm font-medium">
                تصويت سري
              </div>
              <div class="text-xs text-muted-foreground">
                إخفاء أصوات الأعضاء قبل الإغلاق
              </div>
            </div>
            <Switch v-model="workflowSettings.hiddenVoting" />
          </div>
          <div class="flex items-center justify-between rounded-lg border p-3">
            <div class="text-sm font-medium">
              ترجيح صوت المدير عند التعادل
            </div>
            <Switch v-model="workflowSettings.managerWeight" />
          </div>
          <Button>حفظ التغييرات</Button>
        </Card>
      </TabsContent>

      <TabsContent
        value="email"
        class="mt-4"
      >
        <Card class="space-y-5 border-0 p-6 shadow-card">
          <h3 class="font-semibold">
            إعدادات SMTP
          </h3>
          <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
              <Label>SMTP Host</Label>
              <Input v-model="emailSettings.host" />
            </div>
            <div class="space-y-2">
              <Label>Port</Label>
              <Input v-model="emailSettings.port" />
            </div>
            <div class="space-y-2">
              <Label>المستخدم</Label>
              <Input v-model="emailSettings.username" />
            </div>
            <div class="space-y-2">
              <Label>كلمة المرور</Label>
              <Input
                v-model="emailSettings.password"
                type="password"
              />
            </div>
          </div>
          <div class="space-y-2">
            <Label>قالب إشعار اعتماد طلب</Label>
            <Textarea
              v-model="emailSettings.approvalTemplate"
              rows="4"
            />
          </div>
          <Button>حفظ</Button>
        </Card>
      </TabsContent>

      <TabsContent
        value="notif"
        class="mt-4"
      >
        <Card class="space-y-3 border-0 p-6 shadow-card">
          <h3 class="mb-2 font-semibold">
            قنوات الإشعارات
          </h3>
          <div
            v-for="item in notificationSettings"
            :key="item.label"
            class="flex items-center justify-between rounded-lg border p-3"
          >
            <div class="text-sm">
              {{ item.label }}
            </div>
            <Switch v-model="item.enabled" />
          </div>
        </Card>
      </TabsContent>

      <TabsContent
        value="security"
        class="mt-4"
      >
        <Card class="space-y-3 border-0 p-6 shadow-card">
          <h3 class="mb-2 font-semibold">
            سياسات الأمن
          </h3>
          <div
            v-for="item in securitySettings"
            :key="item.label"
            class="flex items-center justify-between rounded-lg border p-3"
          >
            <div class="text-sm">
              {{ item.label }}
            </div>
            <Switch v-model="item.enabled" />
          </div>
        </Card>
      </TabsContent>

      <TabsContent
        value="general"
        class="mt-4"
      >
        <Card class="space-y-5 border-0 p-6 shadow-card">
          <h3 class="font-semibold">
            إعدادات عامة
          </h3>
          <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
              <Label>اسم المنصة</Label>
              <Input v-model="generalSettings.platformName" />
            </div>
            <div class="space-y-2">
              <Label>الجهة</Label>
              <Input v-model="generalSettings.authority" />
            </div>
            <div class="space-y-2">
              <Label>المنطقة الزمنية</Label>
              <Input v-model="generalSettings.timeZone" />
            </div>
          </div>
          <div class="flex items-center justify-between rounded-lg border border-info/20 bg-info/5 p-4">
            <div class="text-xs">
              الوضع الحالي: <span class="font-semibold">{{ isDark ? 'داكن' : 'فاتح' }}</span>
            </div>
            <Button
              variant="outline"
              size="sm"
              @click="toggleTheme"
            >
              {{ isDark ? 'الوضع الفاتح' : 'الوضع الداكن' }}
            </Button>
          </div>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
