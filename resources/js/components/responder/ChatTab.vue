<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { sendMessage } from '@/actions/App/Http/Controllers/ResponderController';
import type { IncidentMessageItem } from '@/types/responder';

const props = defineProps<{
    messages: IncidentMessageItem[];
    incidentId: number;
    currentUserId: number;
}>();

const emit = defineEmits<{
    'messages-read': [];
}>();

const QUICK_REPLIES = [
    'On scene',
    'Need backup',
    'Patient stable',
    'Transporting',
    'All clear',
    'Copy that',
    'Stand by',
    'Negative',
] as const;

const messageText = ref('');
const isSending = ref(false);
const messagesContainer = ref<HTMLDivElement | null>(null);

function scrollToBottom(): void {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
}

onMounted(() => {
    emit('messages-read');
    scrollToBottom();
});

watch(
    () => props.messages.length,
    () => {
        scrollToBottom();
    },
);

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

async function send(body: string, isQuickReply: boolean): Promise<void> {
    if (!body.trim() || isSending.value) {
        return;
    }

    isSending.value = true;

    try {
        await fetch(sendMessage.url({ incident: String(props.incidentId) }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                body: body.trim(),
                is_quick_reply: isQuickReply,
            }),
        });
    } catch {
        // Silent fail for fire-and-forget
    } finally {
        isSending.value = false;
    }
}

async function sendQuickReply(text: string): Promise<void> {
    await send(text, true);
}

async function sendFreeText(): Promise<void> {
    const text = messageText.value;
    messageText.value = '';
    await send(text, false);
}

function formatTime(dateStr: string): string {
    const date = new Date(dateStr);

    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function isOwnMessage(msg: IncidentMessageItem): boolean {
    return msg.sender_id === props.currentUserId;
}
</script>

<template>
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Message history -->
        <div
            ref="messagesContainer"
            class="hide-scrollbar flex flex-1 flex-col gap-2 overflow-y-auto px-4 py-3"
        >
            <div
                v-if="props.messages.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <p class="text-[13px] text-t-text-dim">No messages yet</p>
            </div>

            <div
                v-for="msg in props.messages"
                :key="msg.id"
                class="flex flex-col"
                :class="isOwnMessage(msg) ? 'items-end' : 'items-start'"
            >
                <!-- Sender info -->
                <div class="mb-0.5 flex items-center gap-1.5">
                    <span class="text-[11px] font-medium text-t-text-dim">
                        {{ msg.sender?.name ?? 'System' }}
                    </span>

                    <span
                        v-if="msg.sender?.role"
                        class="rounded bg-t-border/40 px-1 py-0.5 font-mono text-[10px] text-t-text-dim uppercase"
                    >
                        {{ msg.sender.role }}
                    </span>
                </div>

                <!-- Message bubble -->
                <div
                    class="max-w-[85%] rounded-xl px-3 py-2"
                    :class="
                        isOwnMessage(msg)
                            ? 'rounded-br-sm bg-t-accent text-white'
                            : 'rounded-bl-sm bg-t-border/30 text-t-text'
                    "
                >
                    <p class="text-[13px] leading-relaxed">{{ msg.body }}</p>
                </div>

                <!-- Timestamp -->
                <span class="mt-0.5 font-mono text-[10px] text-t-text-dim">
                    {{ formatTime(msg.created_at) }}
                </span>
            </div>
        </div>

        <!-- Quick-reply chips -->
        <div
            class="flex gap-2 overflow-x-auto border-t border-t-border px-4 py-2"
        >
            <button
                v-for="chip in QUICK_REPLIES"
                :key="chip"
                type="button"
                class="min-h-[36px] shrink-0 rounded-[10px] border border-t-accent/40 bg-t-accent/10 px-3 py-1.5 text-[11px] font-medium text-t-accent transition-colors active:bg-t-accent/20"
                :disabled="isSending"
                @click="sendQuickReply(chip)"
            >
                {{ chip }}
            </button>
        </div>

        <!-- Free text input -->
        <div class="flex items-center gap-2 border-t border-t-border px-4 py-2">
            <input
                v-model="messageText"
                type="text"
                placeholder="Type a message..."
                class="min-h-[44px] flex-1 rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text transition-colors outline-none placeholder:text-t-text-dim/50 focus:border-t-accent"
                :disabled="isSending"
                @keydown.enter.prevent="sendFreeText"
            />

            <button
                type="button"
                class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-t-accent text-white transition-colors active:opacity-80 disabled:opacity-50"
                :disabled="isSending || !messageText.trim()"
                @click="sendFreeText"
            >
                <!-- Send arrow icon -->
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        d="M3.105 2.29a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95l14.095-5.637a.75.75 0 000-1.396L3.105 2.289z"
                    />
                </svg>
            </button>
        </div>
    </div>
</template>
