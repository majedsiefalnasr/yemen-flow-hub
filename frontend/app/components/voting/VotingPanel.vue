<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useVotingStore } from '../../stores/voting.store'
import { VoteType, RequestStatus, UserRole } from '../../types/enums'
import type { RequestVote } from '../../types/models'

const props = defineProps<{
  requestId: number
  requestStatus: RequestStatus
  userRole: UserRole
}>()

const votingStore = useVotingStore()
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
const totalMembers = computed(() => detail.value?.total_members ?? 0)
const notYetVotedCount = computed(() => Math.max(0, totalMembers.value - votes.value.length))

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

function voteChipClass(vote: VoteType): string {
  switch (vote) {
    case VoteType.APPROVE: return 'vote-chip--approve'
    case VoteType.REJECT: return 'vote-chip--reject'
    case VoteType.ABSTAIN: return 'vote-chip--abstain'
    case VoteType.AUTO_ABSTAIN_TIMEOUT: return 'vote-chip--auto-abstain'
  }
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
  <div class="voting-panel" dir="rtl">

    <!-- Loading state -->
    <div v-if="votingStore.loadingDetail" class="voting-loading" aria-busy="true">
      <div class="skeleton skeleton--wide" />
      <div class="skeleton skeleton--narrow" />
      <div class="skeleton skeleton--wide" />
    </div>

    <!-- Error state -->
    <div v-else-if="votingStore.voteError && !detail" class="voting-error" role="alert">
      <p>{{ votingStore.voteError }}</p>
      <button class="btn-retry" @click="votingStore.loadVotingDetail(requestId)">إعادة المحاولة</button>
    </div>

    <template v-else-if="detail">

      <!-- Final decision banner: EXECUTIVE_REJECTED -->
      <div v-if="requestStatus === RequestStatus.EXECUTIVE_REJECTED" class="final-banner final-banner--rejected" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <span>قرار نهائي — لا إجراءات إضافية ممكنة</span>
      </div>

      <!-- Final decision badge -->
      <div v-if="isFinalized" class="final-decision" :class="requestStatus === RequestStatus.EXECUTIVE_APPROVED ? 'final-decision--approved' : 'final-decision--rejected'">
        {{ requestStatus === RequestStatus.EXECUTIVE_APPROVED ? 'معتمد' : 'مرفوض' }}
      </div>

      <!-- Vote action error -->
      <div v-if="voteError" class="vote-error" role="alert">{{ voteError }}</div>

      <!-- Tally section -->
      <div v-if="tally" class="tally-section">
        <h3 class="section-title">نتائج التصويت</h3>

        <div class="tally-bar-group">
          <!-- Approve bar -->
          <div class="tally-row">
            <span class="tally-row__label tally-row__label--approve">موافق</span>
            <div class="tally-bar-track">
              <div class="tally-bar tally-bar--approve" :style="{ width: tallyBarWidth(tally.approve_count) }" />
            </div>
            <span class="tally-row__fraction">{{ tally.approve_count }} / {{ totalMembers }}</span>
          </div>

          <!-- Reject bar -->
          <div class="tally-row">
            <span class="tally-row__label tally-row__label--reject">رافض</span>
            <div class="tally-bar-track">
              <div class="tally-bar tally-bar--reject" :style="{ width: tallyBarWidth(tally.reject_count) }" />
            </div>
            <span class="tally-row__fraction">{{ tally.reject_count }} / {{ totalMembers }}</span>
          </div>

          <!-- Abstain bar (manual + auto combined) -->
          <div class="tally-row">
            <span class="tally-row__label tally-row__label--abstain">ممتنع / غائب</span>
            <div class="tally-bar-track">
              <div class="tally-bar tally-bar--abstain" :style="{ width: tallyBarWidth(tally.abstain_count + tally.auto_abstain_count) }" />
            </div>
            <span class="tally-row__fraction">{{ tally.abstain_count + tally.auto_abstain_count }} / {{ totalMembers }}</span>
          </div>
        </div>
      </div>

      <!-- Tie-break notice -->
      <div v-if="showTieBreak" class="tiebreak-notice" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>تعادل — يُرجَّح صوت المدير عند التعادل</span>
      </div>

      <!-- Member roster -->
      <div class="roster-section">
        <h3 class="section-title">حالة أعضاء اللجنة</h3>

        <table class="roster-table" role="table" aria-label="أصوات الأعضاء">
          <thead>
            <tr class="roster-table__header-row">
              <th class="roster-table__th" scope="col">العضو</th>
              <th class="roster-table__th" scope="col">الحالة</th>
              <th class="roster-table__th" scope="col">وقت التصويت</th>
            </tr>
          </thead>
          <tbody>
            <!-- Voted member rows -->
            <tr v-for="v in votes" :key="v.id" class="roster-table__row">
              <td class="roster-table__td">
                <span class="member-name">{{ v.user_name ?? '—' }}</span>
                <span v-if="v.is_director_override" class="director-badge">تجاوز المدير</span>
              </td>
              <td class="roster-table__td">
                <span class="vote-chip" :class="voteChipClass(v.vote)">{{ voteLabel(v.vote) }}</span>
              </td>
              <td class="roster-table__td roster-table__td--mono">
                {{ v.voted_at ? new Date(v.voted_at).toLocaleString('ar-YE') : '—' }}
              </td>
            </tr>
            <!-- Placeholder rows for members who haven't voted yet -->
            <tr
              v-for="n in notYetVotedCount"
              :key="`pending-${n}`"
              class="roster-table__row roster-table__row--pending"
            >
              <td class="roster-table__td member-pending">عضو اللجنة</td>
              <td class="roster-table__td">
                <span class="vote-chip vote-chip--not-voted">لم يصوت بعد</span>
              </td>
              <td class="roster-table__td roster-table__td--mono">—</td>
            </tr>
          </tbody>
        </table>

        <div v-if="votes.length === 0 && notYetVotedCount === 0" class="empty-votes" role="status">
          لا توجد أصوات مسجّلة بعد.
        </div>
      </div>

      <!-- Vote action buttons -->
      <div v-if="canVote && !pendingVote" class="vote-actions">
        <h3 class="section-title">صوّت الآن</h3>
        <div class="vote-buttons-row">
          <button class="action-btn action-btn--approve" @click="selectVote(VoteType.APPROVE)">موافقة</button>
          <button class="action-btn action-btn--reject" @click="selectVote(VoteType.REJECT)">رفض</button>
          <button class="action-btn action-btn--secondary" @click="selectVote(VoteType.ABSTAIN)">امتناع</button>
        </div>
      </div>

      <!-- Vote confirmation step -->
      <div v-if="pendingVote" class="vote-confirm" role="region" aria-label="تأكيد التصويت">
        <p class="vote-confirm__prompt">
          أنت على وشك التصويت بـ <strong>{{ pendingVoteLabel(pendingVote) }}</strong>. هل أنت متأكد؟
        </p>

        <!-- Justification textarea (required for REJECT, optional for others) -->
        <div v-if="pendingVote === VoteType.REJECT" class="justify-group">
          <label class="justify-label" for="vote-justification">
            سبب الرفض <span class="required" aria-hidden="true">*</span>
          </label>
          <textarea
            id="vote-justification"
            v-model="justification"
            class="justify-textarea"
            rows="3"
            placeholder="اكتب سبب الرفض هنا…"
            :aria-invalid="!!justificationError"
          />
          <p v-if="justificationError" class="justify-error" role="alert">{{ justificationError }}</p>
        </div>

        <!-- Optional justification for APPROVE / ABSTAIN -->
        <div v-else class="justify-group">
          <label class="justify-label" for="vote-justification-opt">ملاحظة (اختيارية)</label>
          <textarea
            id="vote-justification-opt"
            v-model="justification"
            class="justify-textarea"
            rows="2"
            placeholder="أضف ملاحظة اختيارية…"
          />
        </div>

        <div class="vote-confirm-actions">
          <button
            class="action-btn"
            :class="pendingVote === VoteType.APPROVE ? 'action-btn--approve' : pendingVote === VoteType.REJECT ? 'action-btn--reject' : 'action-btn--secondary'"
            :disabled="votingStore.performingVote"
            @click="confirmVote"
          >
            {{ votingStore.performingVote ? 'جارٍ التسجيل…' : `تأكيد ${pendingVoteLabel(pendingVote)}` }}
          </button>
          <button
            class="action-btn action-btn--secondary"
            :disabled="votingStore.performingVote"
            @click="cancelVote"
          >
            إلغاء
          </button>
        </div>
      </div>

      <!-- Already voted indicator -->
      <div v-if="isSessionOpen && isVoter && detail.my_vote" class="already-voted" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2.5" aria-hidden="true">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <span>لقد صوّتت بـ <strong>{{ voteLabel(detail.my_vote.vote) }}</strong></span>
      </div>

      <!-- Locked state indicator -->
      <div v-if="isLocked" class="locked-indicator" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8e8e93" stroke-width="2" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
        <span>جلسة التصويت {{ isSessionClosed ? 'مغلقة' : 'منتهية' }} — لا يمكن التصويت</span>
      </div>

    </template>
  </div>
</template>

<style scoped>
.voting-panel {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 24px;
  direction: rtl;
}

/* Loading skeleton */
.voting-loading {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton {
  background: #f5f5f7;
  border-radius: 6px;
  height: 20px;
  animation: pulse 1.4s ease-in-out infinite;
}

.skeleton--wide { width: 100%; }
.skeleton--narrow { width: 60%; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Error */
.voting-error {
  background: #fff0f0;
  border: 1px solid #ff3b3033;
  border-radius: 12px;
  padding: 20px 24px;
  color: #ff3b30;
  display: flex;
  align-items: center;
  gap: 12px;
}

.voting-error p { margin: 0; }

.btn-retry {
  padding: 6px 16px;
  background: #ffffff;
  border: 1px solid #ff3b30;
  border-radius: 8px;
  color: #ff3b30;
  font-size: 13px;
  cursor: pointer;
}

/* Final decision */
.final-decision {
  font-size: 28px;
  font-weight: 700;
  text-align: center;
  padding: 16px;
  border-radius: 12px;
}

.final-decision--approved {
  color: #34c759;
  background: #f0fff4;
  border: 1px solid #34c75933;
}

.final-decision--rejected {
  color: #ff3b30;
  background: #fff0f0;
  border: 1px solid #ff3b3033;
}

/* Final banner */
.final-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 14px 18px;
  font-size: 14px;
  font-weight: 500;
}

.final-banner--rejected {
  background: #7f1d1d;
  color: #fef2f2;
  border: 1px solid #991b1b;
}

/* Vote error */
.vote-error {
  background: #fff0f0;
  border: 1px solid #ff3b3033;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  color: #ff3b30;
}

/* Tally section */
.section-title {
  font-size: 15px;
  font-weight: 500;
  color: #1d1d1f;
  margin: 0 0 12px;
}

.tally-bar-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tally-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.tally-row__label {
  width: 110px;
  font-size: 13px;
  text-align: right;
  flex-shrink: 0;
}

.tally-row__label--approve { color: #5856d6; }
.tally-row__label--reject { color: #ff3b30; }
.tally-row__label--abstain { color: #8e8e93; }

.tally-bar-track {
  flex: 1;
  height: 10px;
  background: #f5f5f7;
  border-radius: 999px;
  overflow: hidden;
}

.tally-bar {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.tally-bar--approve { background: #5856d6; }
.tally-bar--reject { background: #ff3b30; }
.tally-bar--abstain { background: #8e8e93; }

.tally-row__fraction {
  font-size: 12px;
  color: #6e6e73;
  width: 60px;
  text-align: left;
  flex-shrink: 0;
}

/* Member roster */
.roster-table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  overflow: hidden;
}

.roster-table__header-row { background: #f5f5f7; }

.roster-table__th {
  padding: 10px 16px;
  text-align: right;
  font-size: 12px;
  font-weight: 500;
  color: #6e6e73;
  border-bottom: 1px solid #d2d2d7;
}

.roster-table__row { height: 44px; }
.roster-table__row + .roster-table__row { border-top: 1px solid #d2d2d7; }

.roster-table__td {
  padding: 10px 16px;
  font-size: 14px;
  color: #1d1d1f;
  text-align: right;
}

.roster-table__td--mono { font-family: monospace; font-size: 12px; color: #6e6e73; }

.member-name { font-size: 14px; }

.director-badge {
  display: inline-block;
  margin-right: 8px;
  padding: 2px 6px;
  background: #ff9f0a22;
  color: #cc7a00;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
}

.vote-chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
}

.vote-chip--approve { background: #34c75922; color: #1a8a3a; }
.vote-chip--reject { background: #ff3b3022; color: #cc2020; }
.vote-chip--abstain { background: #8e8e9322; color: #5a5a5e; }
.vote-chip--auto-abstain { background: #6e6e7322; color: #4a4a4e; font-style: italic; }

.roster-table__row--pending { opacity: 0.6; }

.member-pending {
  font-size: 13px;
  color: #8e8e93;
  font-style: italic;
}

.vote-chip--not-voted {
  background: #f5f5f7;
  color: #8e8e93;
  border: 1px dashed #d2d2d7;
}

/* Tie-break notice */
.tiebreak-notice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fff9f0;
  border: 1px solid #f5d78a;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #a05a00;
}

.empty-votes {
  font-size: 14px;
  color: #8e8e93;
  padding: 12px 0;
  text-align: center;
}

/* Vote actions */
.vote-actions { display: flex; flex-direction: column; gap: 12px; }

.vote-buttons-row {
  display: flex;
  gap: 12px;
  flex-direction: row-reverse;
  justify-content: flex-start;
}

/* Vote confirmation */
.vote-confirm {
  background: #f9f9fb;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.vote-confirm__prompt { margin: 0; font-size: 15px; color: #1d1d1f; }

.justify-group { display: flex; flex-direction: column; gap: 6px; }

.justify-label { font-size: 13px; font-weight: 500; color: #6e6e73; }

.required { color: #ff3b30; }

.justify-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #d2d2d7;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  color: #1d1d1f;
  background: #ffffff;
  resize: vertical;
  direction: rtl;
}

.justify-textarea:focus { outline: none; border-color: #5856d6; }

.justify-error { font-size: 12px; color: #ff3b30; margin: 0; }

.vote-confirm-actions {
  display: flex;
  gap: 10px;
  flex-direction: row-reverse;
  justify-content: flex-start;
}

/* Action buttons */
.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 44px;
  min-width: 48px;
  padding: 0 20px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: opacity 0.15s;
}

.action-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.action-btn--approve { background: #34c759; color: #ffffff; }
.action-btn--approve:hover:not(:disabled) { opacity: 0.88; }

.action-btn--reject { background: #ff3b30; color: #ffffff; }
.action-btn--reject:hover:not(:disabled) { opacity: 0.88; }

.action-btn--secondary { background: #f5f5f7; color: #1d1d1f; border: 1px solid #d2d2d7; }
.action-btn--secondary:hover:not(:disabled) { background: #e5e5ea; }

/* Already voted */
.already-voted {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #34c759;
  padding: 10px 16px;
  background: #f0fff4;
  border: 1px solid #34c75933;
  border-radius: 8px;
}

/* Locked indicator */
.locked-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #8e8e93;
  padding: 10px 16px;
  background: #f5f5f7;
  border: 1px solid #d2d2d7;
  border-radius: 8px;
}
</style>
