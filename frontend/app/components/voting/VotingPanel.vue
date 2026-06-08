// @parity-exempt — voting sub-component; parity evidence captured at requests/detail-voting page
level
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { AlertCircle } from 'lucide-vue-next'
import { useVotingStore } from '../../stores/voting.store'
import { useAuthStore } from '../../stores/auth.store'
import { VoteType, RequestStatus, UserRole } from '../../types/enums'
import type { RequestVote } from '../../types/models'
import { Button } from '../ui/button'
import { Alert, AlertDescription } from '../ui/alert'
import { Skeleton } from '../ui/skeleton'
import LoadErrorAlert from '../shared/LoadErrorAlert.vue'

const props = withDefaults(
  defineProps<{
    requestId: number
    requestStatus: RequestStatus
    userRole: UserRole
    /** Era gate (Epic 17-E.3): 1 = legacy, 2 = new National Committee. */
    votingRuleVersion?: number
  }>(),
  {
    votingRuleVersion: 1,
  },
)

// New National Committee rules (17-E.3): Approve / Not-Eligible only, no Abstain,
// no Director tie-break. Legacy (v1) keeps Approve / Reject / Abstain + tie-break.
const isV2 = computed(() => props.votingRuleVersion === 2)

const votingStore = useVotingStore()
const authStore = useAuthStore()
const currentUserId = computed(() => authStore.user?.id ?? null)
let isMounted = true
onBeforeUnmount(() => {
  isMounted = false
})

// Vote confirmation flow
const pendingVote = ref<VoteType | null>(null)
const justification = ref('')
const justificationError = ref('')
const voteError = ref('')

const isSessionOpen = computed(() => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_OPEN)

const showTieBreak = computed(() => {
  // No tie-break for new-rule (v2) sessions — even splits resolve to Not-Eligible.
  if (isV2.value || !isSessionOpen.value || !tally.value) return false
  return tally.value.approve_count === tally.value.reject_count && tally.value.approve_count > 0
})
const isSessionClosed = computed(
  () => props.requestStatus === RequestStatus.EXECUTIVE_VOTING_CLOSED,
)
const isFinalized = computed(
  () =>
    props.requestStatus === RequestStatus.EXECUTIVE_APPROVED ||
    props.requestStatus === RequestStatus.EXECUTIVE_REJECTED,
)
const isLocked = computed(() => isSessionClosed.value || isFinalized.value)

const isVoter = computed(
  () =>
    props.userRole === UserRole.EXECUTIVE_MEMBER || props.userRole === UserRole.COMMITTEE_DIRECTOR,
)

const canVote = computed(
  () => isSessionOpen.value && isVoter.value && !votingStore.votingDetail?.my_vote,
)

const detail = computed(() => votingStore.votingDetail)
const tally = computed(() => detail.value?.tally ?? null)
const votes = computed<RequestVote[]>(() => detail.value?.votes ?? [])
const COMMITTEE_SIZE = 6
const displayedVotes = computed<RequestVote[]>(() => votes.value.slice(0, COMMITTEE_SIZE))
const notYetVotedCount = computed(() => Math.max(0, COMMITTEE_SIZE - displayedVotes.value.length))
const revealVoteChoices = computed(() => !isSessionOpen.value)

function tallyBarWidth(count: number): string {
  return `${Math.round((count / COMMITTEE_SIZE) * 100)}%`
}

function voteLabel(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE:
      return 'موافقة'
    case VoteType.REJECT:
      return 'رفض'
    case VoteType.ABSTAIN:
      return 'امتناع'
    case VoteType.AUTO_ABSTAIN_TIMEOUT:
      return 'غائب (أنهاه النظام تلقائيا)'
  }
}

function voteChipClasses(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE:
      return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success/10 text-success'
    case VoteType.REJECT:
      return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-destructive/10 text-destructive'
    case VoteType.ABSTAIN:
      return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-foreground'
    case VoteType.AUTO_ABSTAIN_TIMEOUT:
      return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-foreground italic'
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

// Display-only relabel for new-rule sessions: a REJECT vote is shown as
// "غير مستوفي للشروط / Not Eligible". The stored VoteType is unchanged (D6/D8).
function displayVoteLabel(vote: VoteType): string {
  if (isV2.value && vote === VoteType.REJECT) return 'غير مستوفي للشروط'
  return voteLabel(vote)
}

function pendingVoteLabel(vote: VoteType): string {
  if (vote === VoteType.AUTO_ABSTAIN_TIMEOUT) return ''
  return displayVoteLabel(vote)
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
    justificationError.value = 'اكتب سبب الرفض قبل تسجيل صوت رافض.'
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
  } catch (err: any) {
    if (!isMounted) return
    const msg = err instanceof Error ? err.message : ''
    if (msg.includes('VOTING_SESSION_CLOSED')) {
      votingStore.$patch({ votingDetail: savedDetail })
      voteError.value = 'انتهت جلسة التصويت قبل تسجيل صوتك. حدّث الطلب لمراجعة النتيجة.'
    } else {
      voteError.value = msg || 'تعذّر تسجيل التصويت. تحقق من حالة الجلسة وأعد المحاولة.'
    }
    cancelVote()
  }
}

onMounted(async () => {
  await votingStore.loadVotingDetail(props.requestId)
})
</script>

<template>
  <div class="direction-rtl flex flex-col gap-6 p-6">
    <!-- Loading state -->
    <div
      v-if="votingStore.loadingDetail"
      class="flex flex-col gap-3"
      aria-busy="true"
      aria-label="جارٍ تحميل بيانات التصويت"
    >
      <Skeleton class="h-5 w-full" />
      <Skeleton class="h-5 w-3/5" />
      <Skeleton class="h-5 w-full" />
    </div>

    <!-- Error state -->
    <LoadErrorAlert
      v-else-if="votingStore.voteError && !detail"
      :message="votingStore.voteError"
      title="تعذّر تحميل بيانات التصويت"
      @retry="votingStore.loadVotingDetail(requestId)"
    />

    <template v-else-if="detail">
      <!-- Final decision banner: EXECUTIVE_REJECTED -->
      <Alert
        v-if="requestStatus === RequestStatus.EXECUTIVE_REJECTED"
        class="bg-destructive border-destructive border-0"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          class="text-destructive"
          aria-hidden="true"
        >
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <AlertDescription class="text-destructive text-sm"
          >هذا قرار نهائي ولا يمكن تعديل التصويت أو إعادة فتح الجلسة.</AlertDescription
        >
      </Alert>

      <!-- Final decision badge -->
      <div
        v-if="isFinalized"
        class="font-heading rounded-lg px-4 py-4 text-center text-xl leading-7 font-semibold"
        :class="
          requestStatus === RequestStatus.EXECUTIVE_APPROVED
            ? 'bg-success/10 text-success border border-[var(--color-border-success)]'
            : 'bg-destructive/10 text-destructive border-destructive border'
        "
      >
        {{ requestStatus === RequestStatus.EXECUTIVE_APPROVED ? 'معتمد' : 'مرفوض' }}
      </div>

      <!-- Vote action error -->
      <Alert v-if="voteError" class="border-destructive bg-destructive/10 border">
        <AlertCircle class="text-destructive h-4 w-4" aria-hidden="true" />
        <AlertDescription class="text-destructive text-sm">{{ voteError }}</AlertDescription>
      </Alert>

      <!-- Tally section -->
      <div v-if="tally" class="flex flex-col gap-3">
        <h3 class="text-foreground text-sm font-medium">نتائج التصويت</h3>

        <div class="flex flex-col gap-2.5">
          <!-- Approve bar -->
          <div class="flex items-center gap-3">
            <span class="text-voting w-28 flex-shrink-0 text-right text-xs font-medium"
              >موافقة</span
            >
            <div class="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
              <div
                class="bg-voting h-full rounded-full transition-all"
                :style="{ width: tallyBarWidth(tally.approve_count) }"
              />
            </div>
            <span class="text-muted-foreground w-14 flex-shrink-0 text-start text-xs"
              >{{ tally.approve_count }} / {{ COMMITTEE_SIZE }}</span
            >
          </div>

          <!-- Reject bar -->
          <div class="flex items-center gap-3">
            <span class="text-destructive w-28 flex-shrink-0 text-right text-xs font-medium"
              >رفض</span
            >
            <div class="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
              <div
                class="bg-destructive h-full rounded-full transition-all"
                :style="{ width: tallyBarWidth(tally.reject_count) }"
              />
            </div>
            <span class="text-muted-foreground w-14 flex-shrink-0 text-start text-xs"
              >{{ tally.reject_count }} / {{ COMMITTEE_SIZE }}</span
            >
          </div>

          <!-- Abstain bar (manual + auto combined) -->
          <div class="flex items-center gap-3">
            <span class="text-muted-foreground w-28 flex-shrink-0 text-right text-xs font-medium"
              >امتناع / غياب</span
            >
            <div class="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
              <div
                class="bg-muted0 h-full rounded-full transition-all"
                :style="{ width: tallyBarWidth(tally.abstain_count + tally.auto_abstain_count) }"
              />
            </div>
            <span class="text-muted-foreground w-14 flex-shrink-0 text-start text-xs"
              >{{ tally.abstain_count + tally.auto_abstain_count }} / {{ COMMITTEE_SIZE }}</span
            >
          </div>
        </div>
      </div>

      <!-- Tie-break notice -->
      <Alert
        v-if="showTieBreak"
        class="border border-[var(--color-border-warning)] bg-[var(--color-surface-warning)]"
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          class="text-warning"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <AlertDescription class="text-warning text-sm font-semibold"
          >حدث تعادل، ويُحسم بترجيح صوت المدير.</AlertDescription
        >
      </Alert>

      <!-- Member roster -->
      <div class="flex flex-col gap-3">
        <h3 class="text-foreground text-sm font-medium">حالة أعضاء اللجنة</h3>

        <table class="border-border w-full border-collapse overflow-hidden rounded-lg border">
          <thead>
            <tr class="bg-muted border-border border-b">
              <th class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium">
                العضو
              </th>
              <th class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium">
                الحالة
              </th>
              <th class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium">
                وقت التصويت
              </th>
            </tr>
          </thead>
          <tbody>
            <!-- Voted member rows -->
            <tr
              v-for="v in displayedVotes"
              :key="v.id"
              class="border-border hover:bg-muted h-11 border-t"
            >
              <td class="text-foreground px-4 py-2.5 text-sm">
                <span class="block">{{ v.user_name ?? 'غير متاح' }}</span>
                <span
                  v-if="v.is_director_override"
                  class="text-warning mt-1 inline-block rounded bg-[var(--color-surface-warning)] px-2 py-0.5 text-xs font-medium"
                  >تجاوز المدير</span
                >
              </td>
              <td class="px-4 py-2.5">
                <span :class="maskedVoteChipClasses(v)">{{ maskedVoteLabel(v) }}</span>
              </td>
              <td class="text-muted-foreground px-4 py-2.5 font-mono text-xs">
                {{ v.voted_at ? new Date(v.voted_at).toLocaleString('ar-YE') : 'غير متاح' }}
              </td>
            </tr>
            <!-- Placeholder rows for members who haven't voted yet -->
            <tr
              v-for="n in notYetVotedCount"
              :key="`pending-${n}`"
              class="border-border h-11 border-t opacity-60"
            >
              <td class="text-muted-foreground px-4 py-2.5 text-xs italic">عضو اللجنة</td>
              <td class="px-4 py-2.5">
                <span
                  class="bg-muted text-muted-foreground border-border inline-flex items-center rounded-full border border-dashed px-2.5 py-0.5 text-xs font-medium"
                  >لم يصوت بعد</span
                >
              </td>
              <td class="text-muted-foreground px-4 py-2.5 font-mono text-xs">غير متاح</td>
            </tr>
          </tbody>
        </table>

        <div
          v-if="displayedVotes.length === 0 && notYetVotedCount === 0"
          class="text-muted-foreground py-3 text-center text-sm"
        >
          لا توجد أصوات مسجّلة بعد.
        </div>
      </div>

      <!-- Vote action buttons -->
      <div v-if="canVote && !pendingVote" class="flex flex-col gap-3">
        <h3 class="text-foreground text-sm font-medium">تسجيل صوتك</h3>
        <div class="flex flex-row-reverse gap-3">
          <Button class="h-11 flex-1" @click="selectVote(VoteType.APPROVE)">موافقة</Button>
          <!-- New-rule (v2): "Not Eligible" casts REJECT under the hood; Abstain hidden -->
          <Button
            v-if="isV2"
            variant="destructive"
            class="h-11 flex-1"
            @click="selectVote(VoteType.REJECT)"
            >غير مستوفي للشروط</Button
          >
          <template v-else>
            <Button variant="destructive" class="h-11 flex-1" @click="selectVote(VoteType.REJECT)"
              >رفض</Button
            >
            <Button variant="outline" class="h-11 flex-1" @click="selectVote(VoteType.ABSTAIN)"
              >امتناع</Button
            >
          </template>
        </div>
      </div>

      <!-- Vote confirmation step -->
      <div
        v-if="pendingVote"
        class="bg-muted border-border flex flex-col gap-3.5 rounded-lg border p-5"
      >
        <p class="text-foreground text-sm">
          أنت على وشك التصويت بـ <strong>{{ pendingVoteLabel(pendingVote) }}</strong
          >. لا يمكن تعديل الصوت بعد تسجيله.
        </p>

        <!-- Justification textarea (required for REJECT, optional for others) -->
        <div v-if="pendingVote === VoteType.REJECT" class="flex flex-col gap-1.5">
          <label class="text-muted-foreground text-xs font-medium" for="vote-justification">
            سبب الرفض <span class="text-destructive" aria-hidden="true">*</span>
          </label>
          <textarea
            id="vote-justification"
            v-model="justification"
            class="border-border text-foreground resize-none rounded-lg border p-2.5 text-sm font-normal focus:border-indigo-600 focus:outline-none"
            rows="3"
            placeholder="اكتب سبب الرفض الذي سيظهر في سجل التصويت."
            :aria-invalid="!!justificationError"
          />
          <p v-if="justificationError" class="text-destructive text-xs">{{ justificationError }}</p>
        </div>

        <!-- Optional justification for APPROVE / ABSTAIN -->
        <div v-else class="flex flex-col gap-1.5">
          <label class="text-muted-foreground text-xs font-medium" for="vote-justification-opt"
            >ملاحظة (اختيارية)</label
          >
          <textarea
            id="vote-justification-opt"
            v-model="justification"
            class="border-border text-foreground resize-none rounded-lg border p-2.5 text-sm font-normal focus:border-indigo-600 focus:outline-none"
            rows="2"
            placeholder="أضف ملاحظة اختيارية لسجل التصويت."
          />
        </div>

        <div class="flex flex-row-reverse gap-2.5">
          <Button
            :disabled="votingStore.performingVote"
            class="h-11 flex-1"
            :variant="
              pendingVote === VoteType.REJECT
                ? 'destructive'
                : pendingVote === VoteType.ABSTAIN
                  ? 'outline'
                  : 'default'
            "
            @click="confirmVote"
          >
            {{
              votingStore.performingVote
                ? 'جارٍ تسجيل الصوت...'
                : `تأكيد ${pendingVoteLabel(pendingVote)}`
            }}
          </Button>
          <Button
            variant="outline"
            :disabled="votingStore.performingVote"
            class="h-11 flex-1"
            @click="cancelVote"
          >
            إلغاء
          </Button>
        </div>
      </div>

      <!-- Already voted indicator -->
      <Alert
        v-if="isSessionOpen && isVoter && detail.my_vote"
        class="bg-success/10 border border-[var(--color-border-success)]"
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          class="text-success"
          aria-hidden="true"
        >
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <AlertDescription class="text-success text-sm"
          >لقد صوّتت بـ
          <strong>{{ displayVoteLabel(detail.my_vote.vote) }}</strong></AlertDescription
        >
      </Alert>

      <!-- Locked state indicator -->
      <Alert v-if="isLocked" class="bg-muted border-locked border-0">
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="var(--color-locked)"
          stroke-width="2"
          aria-hidden="true"
        >
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <AlertDescription class="text-muted-foreground text-sm"
          >جلسة التصويت {{ isSessionClosed ? 'مغلقة' : 'منتهية' }}، ولا يمكن التصويت
          الآن.</AlertDescription
        >
      </Alert>
    </template>
  </div>
</template>
