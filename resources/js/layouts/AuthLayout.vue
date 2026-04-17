<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Left panel — dark navy branding -->
        <div
            class="relative hidden w-1/2 overflow-hidden lg:flex"
            style="background: #05101e"
        >
            <!-- Animated shield (background, offset right like brand guide hero) -->
            <svg
                viewBox="0 0 600 680"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                class="pointer-events-none absolute top-1/2 -right-[10%] w-[80%] max-w-[720px] -translate-y-1/2"
                style="animation: fadeIn 1.4s ease forwards 0.4s; opacity: 0"
            >
                <defs>
                    <radialGradient id="shieldGlow" cx="50%" cy="58%" r="50%">
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
                    <radialGradient id="eyeGlow" cx="50%" cy="50%" r="50%">
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
                    <radialGradient id="coreGlow" cx="50%" cy="50%" r="50%">
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

            <!-- Branding text (positioned like brand guide hero) -->
            <div
                class="relative z-10 flex flex-col justify-center px-12 xl:px-16"
            >
                <p
                    class="mb-6 text-[11px] tracking-[3px]"
                    style="
                        color: #378add;
                        font-family: 'DM Mono', monospace;
                        animation: fadeUp 0.8s ease forwards 0.2s;
                        opacity: 0;
                    "
                >
                    INCIDENT RESPONSE MANAGEMENT SYSTEM
                </p>
                <h1
                    class="leading-[0.92] tracking-[4px]"
                    style="
                        font-family: 'Bebas Neue', sans-serif;
                        font-size: clamp(64px, 8vw, 120px);
                        color: #ffffff;
                        animation: fadeUp 0.9s ease forwards 0.4s;
                        opacity: 0;
                    "
                >
                    <span
                        class="block"
                        style="color: #378add"
                    >
                        SENTINEL
                    </span>
                </h1>
                <p
                    class="mt-8 max-w-[360px] text-[15px] font-light leading-relaxed"
                    style="
                        color: #b5d4f4;
                        animation: fadeUp 0.9s ease forwards 0.6s;
                        opacity: 0;
                    "
                >
                    Coordinated dispatch, real-time tracking, and
                    incident lifecycle management.
                </p>
                <p
                    class="mt-10 text-[12px] tracking-[4px]"
                    style="
                        color: #378add;
                        font-family: 'DM Mono', monospace;
                        animation: fadeUp 0.9s ease forwards 0.8s;
                        opacity: 0;
                    "
                >
                    ALWAYS READY. ALWAYS WATCHING.
                </p>
            </div>
        </div>

        <!-- Right panel — form -->
        <div
            class="flex w-full items-center justify-center bg-background p-6 lg:w-1/2"
        >
            <div
                class="w-full max-w-md"
                style="animation: fadeUp 400ms ease"
            >
                <!-- Mobile branding (hidden on lg+) -->
                <div class="mb-8 flex flex-col items-center gap-3 lg:hidden">
                    <div class="flex items-center gap-3">
                        <svg
                            viewBox="0 0 80 91"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-[48px] w-[42px]"
                        >
                            <path
                                d="M40 4L76 18V44C76 66 40 87 40 87C40 87 4 66 4 44V18L40 4Z"
                                fill="#0C447C"
                                stroke="#378ADD"
                                stroke-width="1.5"
                            />
                            <path
                                d="M40 10L70 23V44C70 63 40 82 40 82C40 82 10 63 10 44V23L40 10Z"
                                fill="none"
                                stroke="rgba(55,138,221,0.2)"
                                stroke-width="0.7"
                            />
                            <circle
                                cx="40"
                                cy="52"
                                r="14"
                                fill="none"
                                stroke="#378ADD"
                                stroke-width="1"
                                opacity="0.4"
                            />
                            <circle
                                cx="40"
                                cy="52"
                                r="9.5"
                                fill="none"
                                stroke="#378ADD"
                                stroke-width="1"
                                opacity="0.6"
                            />
                            <circle
                                cx="40"
                                cy="52"
                                r="5.5"
                                fill="#378ADD"
                                opacity="0.9"
                            />
                            <circle cx="40" cy="52" r="2.2" fill="#E6F1FB" />
                            <line
                                x1="26"
                                y1="52"
                                x2="31"
                                y2="52"
                                stroke="#378ADD"
                                stroke-width="1.2"
                                opacity="0.6"
                            />
                            <line
                                x1="49"
                                y1="52"
                                x2="54"
                                y2="52"
                                stroke="#378ADD"
                                stroke-width="1.2"
                                opacity="0.6"
                            />
                            <line
                                x1="40"
                                y1="38"
                                x2="40"
                                y2="43"
                                stroke="#378ADD"
                                stroke-width="1.2"
                                opacity="0.6"
                            />
                            <line
                                x1="40"
                                y1="61"
                                x2="40"
                                y2="66"
                                stroke="#378ADD"
                                stroke-width="1.2"
                                opacity="0.6"
                            />
                            <path
                                d="M27 25 Q40 20 53 25"
                                fill="none"
                                stroke="#378ADD"
                                stroke-width="1"
                                opacity="0.5"
                            />
                            <path
                                d="M21 29 Q40 23 59 29"
                                fill="none"
                                stroke="#378ADD"
                                stroke-width="0.7"
                                opacity="0.3"
                            />
                        </svg>
                        <span
                            class="text-2xl tracking-[3px] text-t-brand"
                            style="font-family: 'Bebas Neue', sans-serif"
                        >
                            SENTINEL
                        </span>
                    </div>
                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Incident Response Management System
                    </span>
                </div>

                <!-- Page title -->
                <div class="mb-6 text-center lg:text-left">
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
    </div>
</template>
