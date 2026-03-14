<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh items-center justify-center bg-background p-6">
        <div
            class="w-full max-w-md rounded-[14px] border border-border bg-card p-10 shadow-[var(--shadow-4)]"
            style="animation: fadeUp 400ms ease"
        >
            <!-- Sentinel branding -->
            <div class="mb-8 flex flex-col items-center gap-2">
                <!-- Full animated Sentinel shield -->
                <div class="h-[136px] w-[120px]">
                    <svg
                        viewBox="0 0 600 680"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-full w-full"
                    >
                        <defs>
                            <radialGradient
                                id="shieldGlow"
                                cx="50%"
                                cy="58%"
                                r="50%"
                            >
                                <stop
                                    offset="0%"
                                    stop-color="#185FA5"
                                    stop-opacity="0.18"
                                />
                                <stop
                                    offset="60%"
                                    stop-color="#042C53"
                                    stop-opacity="0.10"
                                />
                                <stop
                                    offset="100%"
                                    stop-color="#042C53"
                                    stop-opacity="0"
                                />
                            </radialGradient>
                            <radialGradient
                                id="eyeGlow"
                                cx="50%"
                                cy="50%"
                                r="50%"
                            >
                                <stop
                                    offset="0%"
                                    stop-color="#378ADD"
                                    stop-opacity="0.6"
                                />
                                <stop
                                    offset="100%"
                                    stop-color="#185FA5"
                                    stop-opacity="0"
                                />
                            </radialGradient>
                            <radialGradient
                                id="coreGlow"
                                cx="50%"
                                cy="50%"
                                r="50%"
                            >
                                <stop
                                    offset="0%"
                                    stop-color="#85B7EB"
                                    stop-opacity="1"
                                />
                                <stop
                                    offset="100%"
                                    stop-color="#378ADD"
                                    stop-opacity="0.8"
                                />
                            </radialGradient>
                        </defs>

                        <!-- Shield fill glow -->
                        <path
                            d="M300 20L580 120V340C580 480 300 660 300 660C300 660 20 480 20 340V120L300 20Z"
                            fill="url(#shieldGlow)"
                        />

                        <!-- Outer shield edge -->
                        <path
                            d="M300 20L580 120V340C580 480 300 660 300 660C300 660 20 480 20 340V120L300 20Z"
                            fill="none"
                            stroke="rgba(55,138,221,0.28)"
                            stroke-width="1.5"
                        />

                        <!-- Inner shield inset -->
                        <path
                            d="M300 58L548 148V336C548 462 300 630 300 630C300 630 52 462 52 336V148L300 58Z"
                            fill="none"
                            stroke="rgba(55,138,221,0.14)"
                            stroke-width="1"
                        />

                        <!-- Second inset -->
                        <path
                            d="M300 100L516 178V332C516 444 300 600 300 600C300 600 84 444 84 332V178L300 100Z"
                            fill="none"
                            stroke="rgba(55,138,221,0.08)"
                            stroke-width="0.8"
                        />

                        <!-- Radar rings -->
                        <circle
                            cx="300"
                            cy="390"
                            r="230"
                            fill="none"
                            stroke="rgba(55,138,221,0.06)"
                            stroke-width="0.8"
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="185"
                            fill="none"
                            stroke="rgba(55,138,221,0.09)"
                            stroke-width="0.8"
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="140"
                            fill="none"
                            stroke="rgba(55,138,221,0.12)"
                            stroke-width="1"
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="100"
                            fill="none"
                            stroke="rgba(55,138,221,0.17)"
                            stroke-width="1"
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="64"
                            fill="none"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1.2"
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="34"
                            fill="rgba(24,95,165,0.12)"
                            stroke="rgba(55,138,221,0.38)"
                            stroke-width="1.5"
                        />

                        <!-- Crosshair lines -->
                        <line
                            x1="80"
                            y1="390"
                            x2="262"
                            y2="390"
                            stroke="rgba(55,138,221,0.22)"
                            stroke-width="1"
                        />
                        <line
                            x1="338"
                            y1="390"
                            x2="520"
                            y2="390"
                            stroke="rgba(55,138,221,0.22)"
                            stroke-width="1"
                        />
                        <line
                            x1="300"
                            y1="220"
                            x2="300"
                            y2="352"
                            stroke="rgba(55,138,221,0.22)"
                            stroke-width="1"
                        />
                        <line
                            x1="300"
                            y1="428"
                            x2="300"
                            y2="580"
                            stroke="rgba(55,138,221,0.22)"
                            stroke-width="1"
                        />

                        <!-- Crosshair tick marks -->
                        <line
                            x1="256"
                            y1="386"
                            x2="256"
                            y2="394"
                            stroke="rgba(55,138,221,0.35)"
                            stroke-width="1"
                        />
                        <line
                            x1="344"
                            y1="386"
                            x2="344"
                            y2="394"
                            stroke="rgba(55,138,221,0.35)"
                            stroke-width="1"
                        />
                        <line
                            x1="296"
                            y1="346"
                            x2="304"
                            y2="346"
                            stroke="rgba(55,138,221,0.35)"
                            stroke-width="1"
                        />
                        <line
                            x1="296"
                            y1="434"
                            x2="304"
                            y2="434"
                            stroke="rgba(55,138,221,0.35)"
                            stroke-width="1"
                        />

                        <!-- Diagonal corner marks -->
                        <line
                            x1="218"
                            y1="318"
                            x2="226"
                            y2="326"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1"
                        />
                        <line
                            x1="382"
                            y1="318"
                            x2="374"
                            y2="326"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1"
                        />
                        <line
                            x1="218"
                            y1="462"
                            x2="226"
                            y2="454"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1"
                        />
                        <line
                            x1="382"
                            y1="462"
                            x2="374"
                            y2="454"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1"
                        />

                        <!-- Signal arcs at shield top -->
                        <path
                            d="M190 178 Q300 128 410 178"
                            fill="none"
                            stroke="rgba(55,138,221,0.20)"
                            stroke-width="1.2"
                        />
                        <path
                            d="M152 158 Q300 98 448 158"
                            fill="none"
                            stroke="rgba(55,138,221,0.13)"
                            stroke-width="1"
                        />
                        <path
                            d="M118 142 Q300 72 482 142"
                            fill="none"
                            stroke="rgba(55,138,221,0.08)"
                            stroke-width="0.8"
                        />

                        <!-- Eye ambient glow halo -->
                        <circle cx="300" cy="390" r="54" fill="url(#eyeGlow)" />

                        <!-- Eye iris -->
                        <circle
                            cx="300"
                            cy="390"
                            r="16"
                            fill="rgba(55,138,221,0.55)"
                            stroke="rgba(181,212,244,0.4)"
                            stroke-width="1"
                        />

                        <!-- Eye pupil core -->
                        <circle cx="300" cy="390" r="7" fill="url(#coreGlow)" />

                        <!-- Pupil highlight -->
                        <circle
                            cx="303"
                            cy="387"
                            r="2.5"
                            fill="rgba(255,255,255,0.55)"
                        />

                        <!-- Animated pulse ring -->
                        <circle
                            cx="300"
                            cy="390"
                            r="26"
                            fill="none"
                            stroke="rgba(55,138,221,0.5)"
                            stroke-width="1.2"
                            style="
                                transform-origin: 300px 390px;
                                animation: pulseRing 2.4s ease-out infinite;
                            "
                        />
                        <circle
                            cx="300"
                            cy="390"
                            r="26"
                            fill="none"
                            stroke="rgba(55,138,221,0.25)"
                            stroke-width="1"
                            style="
                                transform-origin: 300px 390px;
                                animation: pulseRing 2.4s ease-out infinite 0.8s;
                            "
                        />

                        <!-- Scanning sweep line -->
                        <line
                            x1="300"
                            y1="390"
                            x2="300"
                            y2="294"
                            stroke="rgba(55,138,221,0.18)"
                            stroke-width="1"
                            style="
                                transform-origin: 300px 390px;
                                animation: sweep 4s linear infinite;
                            "
                        />
                    </svg>
                </div>
                <span
                    class="text-2xl tracking-[3px] text-t-brand"
                    style="font-family: 'Bebas Neue', sans-serif"
                >
                    SENTINEL
                </span>
                <span class="text-xs text-muted-foreground">
                    Incident Response Management System
                </span>
            </div>

            <!-- Page title -->
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-foreground">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>

            <slot />
        </div>
    </div>
</template>
