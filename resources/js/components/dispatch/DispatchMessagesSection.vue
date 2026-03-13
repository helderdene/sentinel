<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { sendMessage } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import type { DispatchMessageItem } from '@/types/dispatch';

const props = defineProps<{
    incidentId: string;
    messages: DispatchMessageItem[];
    currentUserId: number;
    expanded: boolean;
    unreadCount?: number;
}>();

const emit = defineEmits<{
    toggle: [];
    send: [message: DispatchMessageItem];
}>();

const QUICK_REPLIES = [
    'Copy',
    'Stand by',
    'Proceed',
    'Return to station',
    'Backup en route',
    'Update status',
    'Acknowledged',
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

watch(
    () => props.messages.length,
    () => {
        if (props.expanded) {
            scrollToBottom();
        }
    },
);

watch(
    () => props.expanded,
    (val) => {
        if (val) {
            scrollToBottom();
        }
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

let nextOptimisticId = -1;

async function send(body: string, isQuickReply: boolean): Promise<void> {
    if (!body.trim() || isSending.value) {
        return;
    }

    isSending.value = true;

    const optimisticMessage: DispatchMessageItem = {
        id: nextOptimisticId--,
        body: body.trim(),
        is_quick_reply: isQuickReply,
        sender_id: props.currentUserId,
        sender_name: 'You',
        sender_role: 'dispatcher',
        sender_unit_callsign: null,
        sent_at: new Date().toISOString(),
    };

    emit('send', optimisticMessage);

    try {
        await fetch(sendMessage.url({ incident: props.incidentId }), {
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
    return new Date(dateStr).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
}

function isOwnMessage(msg: DispatchMessageItem): boolean {
    return msg.sender_id === props.currentUserId;
}

function senderDisplay(msg: DispatchMessageItem): string {
    if (msg.sender_unit_callsign) {
        return `${msg.sender_unit_callsign} \u00B7 ${msg.sender_name}`;
    }

    return msg.sender_name;
}
</script>

<template>
    <div class="border-b border-t-border">
        <!-- Section header -->
        <button
            class="flex w-full items-center justify-between px-3 py-2.5"
            @click="emit('toggle')"
        >
            <span
                class="font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                MESSAGES
                <span
                    v-if="unreadCount && unreadCount > 0"
                    class="ml-1 text-t-accent"
                >
                    ({{ unreadCount }} new)
                </span>
            </span>
            <svg
                class="size-3 text-t-text-faint transition-transform duration-200"
                :class="{ 'rotate-180': expanded }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </button>

        <!-- Collapsible body -->
        <div
            class="grid transition-[grid-template-rows] duration-200"
            :style="{
                gridTemplateRows: expanded ? '1fr' : '0fr',
            }"
        >
            <div class="overflow-hidden">
                <!-- Message list -->
                <div
                    ref="messagesContainer"
                    class="max-h-[200px] space-y-2 overflow-y-auto px-3"
                >
                    <div
                        v-if="messages.length === 0"
                        class="py-4 text-center text-[10px] text-t-text-faint"
                    >
                        No messages yet
                    </div>

                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="isOwnMessage(msg) ? 'items-end' : 'items-start'"
                    >
                        <!-- Sender line -->
                        <div class="mb-0.5 flex items-center gap-1">
                            <span
                                class="font-mono text-[11px] font-medium text-t-text-dim"
                            >
                                {{ senderDisplay(msg) }}
                            </span>
                            <span
                                class="rounded bg-t-border/40 px-1 py-0.5 font-mono text-[10px] text-t-text-dim uppercase"
                            >
                                {{ msg.sender_role }}
                            </span>
                        </div>

                        <!-- Message bubble -->
                        <div
                            class="max-w-[85%] rounded-xl px-2.5 py-1.5"
                            :class="
                                isOwnMessage(msg)
                                    ? 'rounded-br-sm bg-t-accent text-white'
                                    : 'rounded-bl-sm bg-t-border/30 text-t-text'
                            "
                        >
                            <p class="text-xs leading-relaxed">
                                {{ msg.body }}
                            </p>
                        </div>

                        <!-- Timestamp -->
                        <span
                            class="mt-0.5 font-mono text-[10px] text-t-text-dim"
                        >
                            {{ formatTime(msg.sent_at) }}
                        </span>
                    </div>
                </div>

                <!-- Quick-reply chips -->
                <div class="flex flex-wrap gap-1.5 px-3 pt-2">
                    <button
                        v-for="chip in QUICK_REPLIES"
                        :key="chip"
                        type="button"
                        class="rounded border border-t-accent/40 bg-t-accent/10 px-2 py-1 font-mono text-[10px] font-medium text-t-accent transition-colors active:bg-t-accent/20"
                        :disabled="isSending"
                        @click="sendQuickReply(chip)"
                    >
                        {{ chip }}
                    </button>
                </div>

                <!-- Free text input -->
                <div class="flex items-center gap-1.5 px-3 py-2.5">
                    <input
                        v-model="messageText"
                        type="text"
                        placeholder="Type a message..."
                        class="min-h-[32px] flex-1 rounded border border-t-border bg-t-surface px-2.5 py-1.5 text-xs text-t-text outline-none placeholder:text-t-text-dim/50 focus:border-t-accent"
                        :disabled="isSending"
                        @keydown.enter.prevent="sendFreeText"
                    />
                    <button
                        type="button"
                        class="flex size-[32px] shrink-0 items-center justify-center rounded bg-t-accent text-white transition-colors active:opacity-80 disabled:opacity-50"
                        :disabled="isSending || !messageText.trim()"
                        @click="sendFreeText"
                    >
                        <svg
                            class="size-3.5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M3.105 2.29a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95l14.095-5.637a.75.75 0 000-1.396L3.105 2.289z"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
