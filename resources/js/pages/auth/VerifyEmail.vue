<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});
</script>

<template>
    <AuthBase>
        <Head title="Verify email" />

        <div class="flex min-h-screen w-full items-center justify-center bg-[#f8f1f1] px-6 py-10">
            <div class="grid w-full max-w-6xl overflow-hidden rounded-[36px] bg-white shadow-2xl lg:grid-cols-2">
                <!-- LEFT PANEL -->
                <div class="relative hidden min-h-[680px] overflow-hidden bg-[rgb(var(--pup-maroon))] p-14 text-white lg:block">
                    <img
                        src="/images/pup-logo.png"
                        alt="PUP Watermark"
                        class="absolute -bottom-28 -left-20 w-[520px] opacity-10"
                    />

                    <div class="relative z-10 flex h-full flex-col justify-between">
                        <div>
                            <h1 class="mt-32 text-5xl font-black leading-tight">
                                Room Availability
                                <br />
                                System
                            </h1>

                            <p class="mt-8 max-w-md text-lg leading-relaxed text-white/75">
                                Department of Computer Engineering
                            </p>
                        </div>

                        <div class="w-full rounded-3xl border border-white/20 bg-white/10 px-8 py-6 backdrop-blur">
                            <p class="whitespace-nowrap text-center text-xs font-medium uppercase tracking-[0.25em] text-[rgb(var(--pup-gold))]">
                                Polytechnic University of the Philippines
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL -->
                <div class="flex min-h-[680px] items-center justify-center bg-white px-10 py-10">
                    <div class="w-full max-w-md">
                        <div class="text-center">
                            <img
                                src="/images/pup-logo.png"
                                alt="PUP Logo"
                                class="mx-auto h-24 w-24 object-contain"
                            />

                            <h1 class="mt-5 text-4xl font-black text-[rgb(var(--pup-maroon))]">
                                Verify Email
                            </h1>

                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                Please verify your email address by clicking the link we just emailed to you.
                            </p>
                        </div>

                        <div class="my-6 flex items-center gap-4">
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                            <div class="h-3 w-3 rotate-45 bg-[rgb(var(--pup-gold))]" />
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                        </div>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                        >
                            A new verification link has been sent to your email address.
                        </div>

                        <form
                            @submit.prevent="form.post(route('verification.send'))"
                            class="space-y-4"
                        >
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-12 w-full rounded-xl bg-[rgb(var(--pup-maroon))] font-bold text-white hover:bg-[rgb(var(--pup-maroon-dark))]"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />

                                Resend verification email
                            </Button>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="w-full text-center text-sm font-bold text-[rgb(var(--pup-maroon))]"
                            >
                                Log out
                            </Link>
                        </form>

                        <p class="mt-6 text-center text-sm text-muted-foreground">
                            Already verified?

                            <TextLink
                                :href="route('login')"
                                class="font-bold text-[rgb(var(--pup-maroon))]"
                            >
                                Log in
                            </TextLink>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthBase>
</template>