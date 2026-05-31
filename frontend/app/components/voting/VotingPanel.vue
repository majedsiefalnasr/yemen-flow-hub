// @parity-exempt — voting sub-component; parity evidence captured at requests/detail-voting page level
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { AlertCircle } from 'lucide-vue-next'
import { useVotingStore } from '../../stores/voting.store'
import { useAuthStore } from '../../stores/auth.store'
import { VoteType, RequestStatus, UserRole } from '../../types/enums'
import type { RequestVote } from '../../types/models'
import { Button } from '../ui/button'
import { Alert, AlertDescription } from '../ui/alert'

const props = defineProps<{
  requestId: number
  requestStatus: RequestStatus
  userRole: UserRole
}>()

const votingStore = useVotingStore()
const authStore = useAuthStore()
const currentUserId = computed(() => authStore.user?.id ?? null)
let isMounted = true
onBeforeUnmount(() => { isMounted = false })

// Vote confirmation flow
const pendingVote = ref<VoteType | null>(null)
const justification = ref('')
const justificationError = ref('')
const voteError = ref('')

const VOTING_STAGES = new Set([
  RequestStatus.WAITING_FOR_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_OPEN,
  RequestStatus.EXECUTIVE_VOTING_CLOSED,
  RequestStatus.EXECUTIVE_APPROVED,
  RequestStatus.EXECUTIVE_REJECTED,
])

const isSessionOpen = computed(() => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_OPEN)

const showTieBreak = computed(() => {
  if (!isSessionOpen.value || !tally.value) return false
  return tally.value.approve_count === tally.value.reject_count && tally.value.approve_count > 0
})
const isSessionClosed = computed(() => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_CLOSED)
const isFinalized = computed(() =>
  props.requestStatus === RequestStatus.EXECUTIVE_APPROVED
  || props.requestStatus === RequestStatus.EXECUTIVE_REJECTED,
)
const isLocked = computed(() => isSessionClosed.value || isFinalized.value)

const isVoter = computed(() =>
  props.userRole === UserRole.EXECUTIVE_MEMBER || props.userRole === UserRole.COMMITTEE_DIRECTOR,
)

const canVote = computed(() =>
  isSessionOpen.value && isVoter.value && !votingStore.votingDetail?.my_vote,
)

const detail = computed(() => votingStore.votingDetail)
const tally = computed(() => detail.value?.tally ?? null)
const votes = computed<RequestVote[]>(() => detail.value?.votes ?? [])
const COMMITTEE_SIZE = 6
const totalMembers = computed(() => COMMITTEE_SIZE)
const displayedVotes = computed<RequestVote[]>(() => votes.value.slice(0, COMMITTEE_SIZE))
const notYetVotedCount = computed(() => Math.max(0, COMMITTEE_SIZE - displayedVotes.value.length))
const revealVoteChoices = computed(() => !isSessionOpen.value)

function tallyBarWidth(count: number): string {
  if (!totalMembers.value) return '0%'
  return `${Math.round((count / totalMembers.value) * 100)}%`
}

function voteLabel(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'موافق'
    case VoteType.REJECT: return 'رافض'
    case VoteType.ABSTAIN: return 'ممتنع'
    case VoteType.AUTO_ABSTAIN_TIMEOUT: return 'غائب (مُنهي تلقائياً)'
  }
}

function voteChipClasses(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success/10 text-success'
    case VoteType.REJECT: return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-destructive/10 text-destructive'
    case VoteType.ABSTAIN: return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-foreground'
    case VoteType.AUTO_ABSTAIN_TIMEOUT: return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-foreground italic'
  }
}

// During open voting we mask other members' choices to preserve secret-ballot.
// The current viewer's OWN vote is always revealed so they can verify what they
// cast without waiting for session closure.
function isOwnVote(voter: RequestVote): boolean {
  return currentUserId.value != null && voter.user_id === currentUserId.value
}

function maskedVoteLabel(voter: RequestVote): string {
  if (revealVoteChoices.value || isOwnVote(voter)) return voteLabel(voter.vote)
  return 'تم التصويت'
}

function maskedVoteChipClasses(voter: RequestVote): string {
  if (revealVoteChoices.value || isOwnVote(voter)) return voteChipClasses(voter.vote)
  return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#5856d6]/10 text-[#5856d6]'
}

function pendingVoteLabel(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'موافقة'
    case VoteType.REJECT: return 'رفض'
    case VoteType.ABSTAIN: return 'امتناع'
    default: return ''
  }
}

function selectVote(vote: VoteType) {
  pendingVote.value = vote
  justification.value = ''
  justificationError.value = ''
  voteError.value = ''
}

function cancelVote() {
  pendingVote.value = null
  justification.value = ''
  justificationError.value = ''
  voteError.value = ''
}

async function confirmVote() {
  if (!pendingVote.value) return

  if (pendingVote.value === VoteType.REJECT && !justification.value.trim()) {
    justificationError.value = 'سبب الرفض مطلوب عند التصويت بالرفض.'
    return
  }

  voteError.value = ''
  justificationError.value = ''

  const voteToSend = pendingVote.value
  const savedDetail = votingStore.votingDetail

  try {
    await votingStore.castVote(props.requestId, voteToSend, justification.value.trim() || undefined)
    if (!isMounted) return
    cancelVote()
  }
  catch (err: unknown) {
    if (!isMounted) return
    const msg = err instanceof Error ? err.message : ''
    if (msg.includes('VOTING_SESSION_CLOSED')) {
      votingStore.$patch({ votingDetail: savedDetail })
      voteError.value = 'انتهت جلسة التصويت. تم إلغاء صوتك.'
    }
    else {
      voteError.value = msg || 'تعذّر تسجيل الصوت. يرجى المحاولة مرة أخرى.'
    }
    cancelVote()
  }
}

onMounted(async () => {
  await votingStore.loadVotingDetail(props.requestId)
})
</script>

<template>
  <div class="flex flex-col gap-6 p-6 direction-rtl" >

    <!-- Loading state -->
    <div v-if="votingStore.loadingDetail" class="flex flex-col gap-3" aria-busy="true">
      <div class="h-5 bg-muted rounded animate-pulse w-full" />
      <div class="h-5 bg-muted rounded animate-pulse w-3/5" />
      <div class="h-5 bg-muted rounded animate-pulse w-full" />
    </div>

    <!-- Error state -->
    <Alert v-else-if="votingStore.voteError && !detail" class="border-s-4 border-s-red-600 bg-destructive/10 border-0">
      <AlertCircle class="h-4 w-4 text-destructive" aria-hidden="true" />
      <AlertDescription class="text-destructive text-sm">
        {{ votingStore.voteError }}
        <Button variant="outline" size="sm" class="ms-3 h-7" @click="votingStore.loadVotingDetail(requestId)">إعادة المحاولة</Button>
      </AlertDescription>
    </Alert>

    <template v-else-if="detail">

      <!-- Final decision banner: EXECUTIVE_REJECTED -->
      <Alert v-if="requestStatus === RequestStatus.EXECUTIVE_REJECTED" class="border-0 bg-destructive border-s-4 border-s-destructive">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-destructive" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <AlertDescription class="text-destructive text-sm">قرار نهائي — لا إجراءات إضافية ممكنة</AlertDescription>
      </Alert>

      <!-- Final decision badge -->
      <div v-if="isFinalized" class="text-center py-4 px-4 rounded-lg font-bold text-2xl" :class="requestStatus === RequestStatus.EXECUTIVE_APPROVED ? 'bg-success/10/10 text-success border border-[var(--color-border-success)]' : 'bg-destructive/10 text-destructive border border-destructive'">
        {{ requestStatus === RequestStatus.EXECUTIVE_APPROVED ? 'معتمد' : 'مرفوض' }}
      </div>

      <!-- Vote action error -->
      <Alert v-if="voteError" class="border-s-4 border-s-red-600 bg-destructive/10 border-0">
        <AlertCircle class="h-4 w-4 text-destructive" aria-hidden="true" />
        <AlertDescription class="text-destructive text-sm">{{ voteError }}</AlertDescription>
      </Alert>

      <!-- Tally section -->
      <div v-if="tally" class="flex flex-col gap-3">
        <h3 class="text-sm font-medium text-foreground">نتائج التصويت</h3>

        <div class="flex flex-col gap-2.5">
          <!-- Approve bar -->
          <div class="flex items-center gap-3">
            <span class="w-28 text-xs text-right text-voting font-medium flex-shrink-0">موافق</span>
            <div class="flex-1 h-2.5 bg-muted rounded-full overflow-hidden">
              <div class="h-full bg-voting rounded-full transition-all" :style="{ width: tallyBarWidth(tally.approve_count) }" />
            </div>
            <span class="text-xs text-muted-foreground w-14 text-start flex-shrink-0">{{ tally.approve_count }} / {{ totalMembers }}</span>
          </div>

          <!-- Reject bar -->
          <div class="flex items-center gap-3">
            <span class="w-28 text-xs text-right text-destructive font-medium flex-shrink-0">رافض</span>
            <div class="flex-1 h-2.5 bg-muted rounded-full overflow-hidden">
              <div class="h-full bg-destructive rounded-full transition-all" :style="{ width: tallyBarWidth(tally.reject_count) }" />
            </div>
            <span class="text-xs text-muted-foreground w-14 text-start flex-shrink-0">{{ tally.reject_count }} / {{ totalMembers }}</span>
          </div>

          <!-- Abstain bar (manual + auto combined) -->
          <div class="flex items-center gap-3">
            <span class="w-28 text-xs text-right text-muted-foreground font-medium flex-shrink-0">ممتنع / غائب</span>
            <div class="flex-1 h-2.5 bg-muted rounded-full overflow-hidden">
              <div class="h-full bg-muted0 rounded-full transition-all" :style="{ width: tallyBarWidth(tally.abstain_count + tally.auto_abstain_count) }" />
            </div>
            <span class="text-xs text-muted-foreground w-14 text-start flex-shrink-0">{{ tally.abstain_count + tally.auto_abstain_count }} / {{ totalMembers }}</span>
          </div>
        </div>
      </div>

      <!-- Tie-break notice -->
      <Alert v-if="showTieBreak" class="border-s-4 border-s-amber-500 bg-[var(--color-surface-warning)] border-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-warning" aria-hidden="true">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <AlertDescription class="text-warning text-sm font-semibold">تعادل — يُرجَّح صوت المدير عند التعادل</AlertDescription>
      </Alert>

      <!-- Member roster -->
      <div class="flex flex-col gap-3">
        <h3 class="text-sm font-medium text-foreground">حالة أعضاء اللجنة</h3>

        <table class="w-full border-collapse border border-border rounded-lg overflow-hidden">
          <thead>
            <tr class="bg-muted border-b border-border">
              <th class="text-right px-4 py-2.5 text-xs font-medium text-muted-foreground">العضو</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-muted-foreground">الحالة</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-muted-foreground">وقت التصويت</th>
            </tr>
          </thead>
          <tbody>
            <!-- Voted member rows -->
            <tr v-for="v in displayedVotes" :key="v.id" class="border-t border-border h-11 hover:bg-muted">
              <td class="px-4 py-2.5 text-sm text-foreground">
                <span class="block">{{ v.user_name ?? '—' }}</span>
                <span v-if="v.is_director_override" class="inline-block mt-1 bg-[var(--color-surface-warning)] text-warning px-2 py-0.5 rounded text-xs font-medium">تجاوز المدير</span>
              </td>
              <td class="px-4 py-2.5">
                <span :class="maskedVoteChipClasses(v)">{{ maskedVoteLabel(v) }}</span>
              </td>
              <td class="px-4 py-2.5 text-xs text-muted-foreground font-mono">
                {{ v.voted_at ? new Date(v.voted_at).toLocaleString('ar-YE') : '—' }}
              </td>
            </tr>
            <!-- Placeholder rows for members who haven't voted yet -->
            <tr
              v-for="n in notYetVotedCount"
              :key="`pending-${n}`"
              class="border-t border-border h-11 opacity-60"
            >
              <td class="px-4 py-2.5 text-xs text-muted-foreground italic">عضو اللجنة</td>
              <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground border border-dashed border-border">لم يصوت بعد</span>
              </td>
              <td class="px-4 py-2.5 text-xs text-muted-foreground font-mono">—</td>
            </tr>
          </tbody>
        </table>

        <div v-if="displayedVotes.length === 0 && notYetVotedCount === 0" class="text-center text-sm text-muted-foreground py-3">
          لا توجد أصوات مسجّلة بعد.
        </div>
      </div>

      <!-- Vote action buttons -->
      <div v-if="canVote && !pendingVote" class="flex flex-col gap-3">
        <h3 class="text-sm font-medium text-foreground">صوّت الآن</h3>
        <div class="flex gap-3 flex-row-reverse">
          <Button @click="selectVote(VoteType.APPROVE)" class="flex-1 h-11 bg-success/10 hover:bg-success/10 text-white">موافقة</Button>
          <Button @click="selectVote(VoteType.REJECT)" class="flex-1 h-11 bg-destructive hover:opacity-90 text-white">رفض</Button>
          <Button variant="outline" @click="selectVote(VoteType.ABSTAIN)" class="flex-1 h-11">امتناع</Button>
        </div>
      </div>

      <!-- Vote confirmation step -->
      <div v-if="pendingVote" class="flex flex-col gap-3.5 bg-muted border border-border rounded-lg p-5">
        <p class="text-sm text-foreground">
          أنت على وشك التصويت بـ <strong>{{ pendingVoteLabel(pendingVote) }}</strong>. هل أنت متأكد؟
        </p>

        <!-- Justification textarea (required for REJECT, optional for others) -->
        <div v-if="pendingVote === VoteType.REJECT" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-muted-foreground" for="vote-justification">
            سبب الرفض <span class="text-destructive" aria-hidden="true">*</span>
          </label>
          <textarea
            id="vote-justification"
            v-model="justification"
            class="p-2.5 border border-border rounded-lg text-sm font-normal text-foreground resize-none focus:outline-none focus:border-indigo-600"
            rows="3"
            placeholder="اكتب سبب الرفض هنا…"
            :aria-invalid="!!justificationError"
            
          />
          <p v-if="justificationError" class="text-xs text-destructive">{{ justificationError }}</p>
        </div>

        <!-- Optional justification for APPROVE / ABSTAIN -->
        <div v-else class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-muted-foreground" for="vote-justification-opt">ملاحظة (اختيارية)</label>
          <textarea
            id="vote-justification-opt"
            v-model="justification"
            class="p-2.5 border border-border rounded-lg text-sm font-normal text-foreground resize-none focus:outline-none focus:border-indigo-600"
            rows="2"
            placeholder="أضف ملاحظة اختيارية…"
            
          />
        </div>

        <div class="flex gap-2.5 flex-row-reverse">
          <Button
            :disabled="votingStore.performingVote"
            class="flex-1 h-11"
            :class="pendingVote === VoteType.APPROVE ? 'bg-success/10 hover:bg-success/10 text-white' : pendingVote === VoteType.REJECT ? 'bg-destructive hover:opacity-90 text-white' : ''"
            :variant="pendingVote === VoteType.APPROVE || pendingVote === VoteType.REJECT ? 'default' : 'outline'"
            @click="confirmVote"
          >
            {{ votingStore.performingVote ? 'جارٍ التسجيل…' : `تأكيد ${pendingVoteLabel(pendingVote)}` }}
          </Button>
          <Button
            variant="outline"
            :disabled="votingStore.performingVote"
            class="flex-1 h-11"
            @click="cancelVote"
          >
            إلغاء
          </Button>
        </div>
      </div>

      <!-- Already voted indicator -->
      <Alert v-if="isSessionOpen && isVoter && detail.my_vote" class="border-0 bg-success/10/10 border-s-4 border-s-green-600">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-success" aria-hidden="true">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <AlertDescription class="text-success text-sm">لقد صوّتت بـ <strong>{{ voteLabel(detail.my_vote.vote) }}</strong></AlertDescription>
      </Alert>

      <!-- Locked state indicator -->
      <Alert v-if="isLocked" class="border-0 bg-muted border-s-4 border-s-locked">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-locked)" stroke-width="2" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <AlertDescription class="text-muted-foreground text-sm">جلسة التصويت {{ isSessionClosed ? 'مغلقة' : 'منتهية' }} — لا يمكن التصويت</AlertDescription>
      </Alert>

    </template>
  </div>
</template>
