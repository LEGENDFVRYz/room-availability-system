<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthBase>
        <Head title="Confirm password" />

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
                                Confirm Password
                            </h1>

                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                Please confirm your password before continuing.
                            </p>
                        </div>

                        <div class="my-6 flex items-center gap-4">
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                            <div class="h-3 w-3 rotate-45 bg-[rgb(var(--pup-gold))]" />
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="space-y-1">
                                <Label for="password" class="text-xs font-semibold text-black">
                                    Password
                                </Label>

                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    autofocus
                                    autocomplete="current-password"
                                    v-model="form.password"
                                    placeholder="Enter your password"
                                    class="h-10 rounded-xl border border-gray-300 bg-white px-4 text-sm text-black placeholder:text-gray-400 shadow-none"
                                />

                                <InputError :message="form.errors.password" />
                            </div>

                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="mt-5 h-12 w-full rounded-xl bg-[rgb(var(--pup-maroon))] font-bold text-white hover:bg-[rgb(var(--pup-maroon-dark))]"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />

                                Confirm Password
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthBase>
</template>