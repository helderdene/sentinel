<script setup lang="ts">
const currentYear = new Date().getFullYear();
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Header -->
        <div
            class="shrink-0 border-b border-t-border bg-t-surface px-[18px] py-4 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <div class="text-[18px] font-bold text-t-text">
                About
            </div>
        </div>

        <div
            class="hide-scrollbar flex-1 overflow-y-auto px-[18px] pb-5 pt-5"
        >
            <!-- Brand card -->
            <div
                class="mb-4 flex flex-col items-center rounded-2xl px-5 py-6 text-center"
                style="background-color: var(--t-brand)"
            >
                <div
                    class="mb-3.5 flex h-16 w-16 items-center justify-center rounded-[18px]"
                    style="background: rgba(255, 255, 255, 0.12)"
                >
                    <svg
                        width="36"
                        height="36"
                        viewBox="0 0 28 28"
                        fill="none"
                    >
                        <path
                            d="M14 2L24 7V14C24 20 19.6 25 14 26C8.4 25 4 20 4 14V7L14 2Z"
                            stroke="#ffffff"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M9 14L12.5 17.5L19 11"
                            stroke="#ffffff"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <div
                    class="mb-1 text-[18px] font-extrabold text-white"
                >
                    CDRRMO Butuan City
                </div>
                <div
                    class="text-[12px] leading-relaxed"
                    style="color: rgba(255, 255, 255, 0.6)"
                >
                    City Disaster Risk Reduction and Management
                    Office
                </div>
            </div>

            <!-- Info sections -->
            <div
                v-for="section in [
                    {
                        t: 'About This App',
                        d: 'The IRMS Citizen Reporting App allows residents of Butuan City to report emergencies directly to CDRRMO. Reports are routed to trained operators for immediate response.',
                    },
                    {
                        t: 'How It Works',
                        d: null,
                        steps: [
                            'Select emergency type',
                            'Provide location & details',
                            'Track your report status',
                        ],
                    },
                    {
                        t: 'Data Privacy',
                        d: 'Your contact information is used solely for emergency follow-up and is protected under the Data Privacy Act of 2012. No account registration required.',
                    },
                    {
                        t: 'Emergency Contacts',
                        d: null,
                        contacts: [
                            {
                                label: 'CDRRMO Hotline',
                                number: '0917-123-4567',
                            },
                            {
                                label:
                                    'National Emergency Hotline',
                                number: '911',
                            },
                        ],
                    },
                ]"
                :key="section.t"
                class="mb-2.5 rounded-[13px] border border-t-border bg-t-surface px-4 py-3.5 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
            >
                <div
                    class="mb-1.5 text-[14px] font-bold text-t-text"
                >
                    {{ section.t }}
                </div>

                <div
                    v-if="section.d"
                    class="text-[13px] leading-relaxed text-t-text-mid"
                >
                    {{ section.d }}
                </div>

                <!-- How it works steps -->
                <div v-if="section.steps" class="mt-2">
                    <div
                        v-for="(step, i) in section.steps"
                        :key="i"
                        class="mb-2 flex items-center gap-3"
                    >
                        <div
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold text-white"
                            style="
                                background-color: var(
                                    --t-accent
                                );
                            "
                        >
                            {{ i + 1 }}
                        </div>
                        <div
                            class="text-[13px] text-t-text-mid"
                        >
                            {{ step }}
                        </div>
                    </div>
                </div>

                <!-- Emergency contacts -->
                <div v-if="section.contacts" class="mt-2">
                    <div
                        v-for="contact in section.contacts"
                        :key="contact.label"
                        class="mb-2 flex items-center justify-between"
                    >
                        <span
                            class="text-[13px] text-t-text-mid"
                            >{{ contact.label }}</span
                        >
                        <span
                            class="font-mono text-[15px] font-bold text-t-text"
                            >{{ contact.number }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="py-4 text-center font-mono text-[11px] text-t-text-faint"
            >
                IRMS Citizen App v1.0 &middot; {{ currentYear }}
            </div>
        </div>
    </div>
</template>
