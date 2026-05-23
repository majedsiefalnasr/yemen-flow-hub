<script setup lang="ts">
import { AlertTriangle, Check, Crown, Gavel, Lock, Minus, PlayCircle, StopCircle, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { RequestStatus, UserRole, VoteType } from '@/types/enums'
import type { VotingDetail } from '@/types/models'
import { useAuthStore } from '@/stores/auth.store'

const props = defineProps<{
  requestId: number
  requestStatus: RequestStatus
  votingDetail: VotingDetail | null
  performing?: boolean
}>()

const emit = defineEmits<{
  vote: [vote: VoteType, justification?: string]
  openSession: []
  closeSession: []
}>()

const authStore = useAuthStore()
const justification = ref('')

const isDirector = computed(() => authStore.user?.role === UserRole.COMMITTEE_DIRECTOR)
const isMember = computed(() =>
  authStore.user?.role === UserRole.EXECUTIVE_MEMBER
  || authStore.user?.role === UserRole.COMMITTEE_DIRECTOR,
)

const sessionOpen = computed(() => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_OPEN)
const sessionClosed = computed(() => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_CLOSED)

const tally = computed(() => props.votingDetail?.tally ?? null)
const votes = computed(() => props.votingDetail?.votes ?? [])
const myVote = computed(() => props.votingDetail?.my_vote ?? null)
const totalMembers = computed(() => props.votingDetail?.total_members ?? 0)
const totalVoted = computed(() => tally.value?.total_cast ?? 0)

const isTie = computed(() =>
  tally.value
  && !tally.value.is_decided
  && totalVoted.value >= totalMembers.value
  && tally.value.approve_count === tally.value.reject_count,
)

function submitVote(value: VoteType) {
  emit('vote', value, justification.value || undefined)
  justification.value = ''
}

function voteLabel(vote: VoteType): string {
  if (vote === VoteType.APPROVE) return 'موافق'
  if (vote === VoteType.REJECT) return 'رافض'
  if (vote === VoteType.ABSTAIN || vote === VoteType.AUTO_ABSTAIN_TIMEOUT) return 'ممتنع'
  return vote
}
</script>

<template>
  <div class="divide-y rounded-xl border bg-card">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
      <div>
        <div class="flex items-center gap-2 font-semibold">
          <Gavel class="h-4 w-4" />
          جلسة تصويت اللجنة التنفيذية
        </div>
        <div class="mt-0.5 text-xs text-muted-foreground">
          النصاب: 4 أصوات للاعتماد أو الرفض · صوّت {{ totalVoted }} من {{ totalMembers }}
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <span
          v-if="sessionClosed && tally"
          :class="cn(
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold',
            tally.result === 'APPROVED' ? 'bg-success/15 text-success' : 'bg-destructive/15 text-destructive',
          )"
        >
          <Lock class="h-3.5 w-3.5" />
          تم إغلاق التصويت — {{ tally.result === 'APPROVED' ? 'مُعتمد' : 'مرفوض' }}
        </span>
        <span
          v-else-if="sessionOpen"
          class="rounded-full bg-info/15 px-3 py-1.5 text-xs font-medium text-info"
        >
          باب التصويت مفتوح
        </span>
        <span
          v-else
          class="rounded-full bg-muted px-3 py-1.5 text-xs font-medium text-muted-foreground"
        >
          باب التصويت مغلق — بانتظار فتحه من قبل مدير اللجنة
        </span>

        <Button
          v-if="isDirector && !sessionOpen && !sessionClosed"
          size="sm"
          :disabled="performing"
          @click="emit('openSession')"
        >
          <PlayCircle class="ms-1 h-4 w-4" />
          فتح باب التصويت
        </Button>
        <Button
          v-if="isDirector && sessionOpen"
          size="sm"
          variant="destructive"
          :disabled="performing"
          @click="emit('closeSession')"
        >
          <StopCircle class="ms-1 h-4 w-4" />
          إغلاق باب التصويت
        </Button>
      </div>
    </div>

    <div
      v-if="tally"
      class="grid gap-3 p-4 sm:grid-cols-3"
    >
      <div class="rounded-lg border p-3">
        <div class="mb-1.5 flex items-center justify-between">
          <div class="text-xs text-muted-foreground">
            موافقة
          </div>
          <div class="font-bold tabular-nums">
            {{ tally.approve_count }}
          </div>
        </div>
        <Progress
          class="h-2 [&_[data-slot=progress-indicator]]:bg-success"
          :model-value="Math.min(100, (tally.approve_count / 4) * 100)"
        />
      </div>
      <div class="rounded-lg border p-3">
        <div class="mb-1.5 flex items-center justify-between">
          <div class="text-xs text-muted-foreground">
            رفض
          </div>
          <div class="font-bold tabular-nums">
            {{ tally.reject_count }}
          </div>
        </div>
        <Progress
          class="h-2 [&_[data-slot=progress-indicator]]:bg-destructive"
          :model-value="Math.min(100, (tally.reject_count / 4) * 100)"
        />
      </div>
      <div class="rounded-lg border p-3">
        <div class="mb-1.5 flex items-center justify-between">
          <div class="text-xs text-muted-foreground">
            امتناع
          </div>
          <div class="font-bold tabular-nums">
            {{ tally.abstain_count + tally.auto_abstain_count }}
          </div>
        </div>
        <Progress
          class="h-2 [&_[data-slot=progress-indicator]]:bg-muted-foreground"
          :model-value="Math.min(100, ((tally.abstain_count + tally.auto_abstain_count) / Math.max(totalMembers, 1)) * 100)"
        />
      </div>
    </div>

    <div
      v-if="votes.length > 0"
      class="p-4"
    >
      <div class="mb-2 text-xs font-semibold text-muted-foreground">
        أعضاء اللجنة
      </div>
      <div class="space-y-1.5">
        <div
          v-for="vote in votes"
          :key="vote.id"
          class="flex items-center gap-3 rounded-lg p-2 hover:bg-muted/40"
        >
          <Avatar size="sm">
            <AvatarFallback class="bg-primary/10 text-xs font-bold text-primary">
              {{ (vote.user_name ?? '?').slice(0, 2) }}
            </AvatarFallback>
          </Avatar>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 text-sm font-medium">
              {{ vote.user_name ?? 'عضو' }}
              <Crown
                v-if="vote.is_director_override"
                class="h-3.5 w-3.5 text-accent"
              />
            </div>
          </div>
          <span
            v-if="vote.voted_at"
            :class="cn(
              'rounded-full px-2 py-0.5 text-[11px] font-medium',
              vote.vote === VoteType.APPROVE && 'bg-success/15 text-success',
              vote.vote === VoteType.REJECT && 'bg-destructive/15 text-destructive',
              (vote.vote === VoteType.ABSTAIN || vote.vote === VoteType.AUTO_ABSTAIN_TIMEOUT) && 'bg-muted text-muted-foreground',
            )"
          >
            {{ voteLabel(vote.vote) }}
          </span>
          <span
            v-else
            class="text-[11px] text-muted-foreground"
          >
            لم يصوّت
          </span>
        </div>
      </div>
    </div>

    <div
      v-if="isMember && sessionOpen"
      class="bg-muted/30 p-4"
    >
      <div class="mb-2 text-xs font-semibold">
        {{
          myVote
            ? `تصويتك الحالي: ${voteLabel(myVote.vote)} — يمكنك تغييره طالما الجلسة مفتوحة`
            : 'صوّت الآن'
        }}
      </div>
      <Textarea
        v-model="justification"
        rows="2"
        class="mb-2"
        placeholder="مبررات (اختياري)"
      />
      <div class="flex flex-wrap gap-2">
        <Button
          size="sm"
          class="bg-success text-success-foreground hover:bg-success/90"
          :disabled="performing"
          @click="submitVote(VoteType.APPROVE)"
        >
          <Check class="ms-1 h-4 w-4" />
          موافق
        </Button>
        <Button
          size="sm"
          variant="destructive"
          :disabled="performing"
          @click="submitVote(VoteType.REJECT)"
        >
          <X class="ms-1 h-4 w-4" />
          رافض
        </Button>
        <Button
          size="sm"
          variant="outline"
          :disabled="performing"
          @click="submitVote(VoteType.ABSTAIN)"
        >
          <Minus class="ms-1 h-4 w-4" />
          ممتنع
        </Button>
      </div>
    </div>

    <div
      v-if="isTie"
      class="border-t-2 border-warning/30 bg-warning/5 p-4"
    >
      <div class="flex items-start gap-3">
        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-warning" />
        <div class="text-sm">
          <div class="font-semibold">
            تعادل في التصويت ({{ tally!.approve_count }} مقابل {{ tally!.reject_count }})
          </div>
          <div class="mt-1 text-xs text-muted-foreground">
            {{
              isDirector
                ? 'بصفتك مدير اللجنة، صوتك حاسم — اختر موافقة أو رفض أعلاه ليُعتمد القرار النهائي تلقائياً.'
                : 'بانتظار صوت مدير اللجنة الحاسم لإصدار القرار النهائي.'
            }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
