<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Lock } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false as boolean,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase>
        <Head title="Log in" />

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
                                Room Availability System
                            </h1>

                            <p class="mt-8 max-w-md text-lg leading-relaxed text-white/75">
                                Department of Computer Engineering
                            </p>
                        </div>

                        <div
                            class="w-full rounded-3xl border border-white/20 bg-white/10 px-8 py-6 backdrop-blur"
                        >
                            <p
                                class="whitespace-nowrap text-center text-xs font-medium uppercase tracking-[0.25em] text-[rgb(var(--pup-gold))]"
                            >
                                Polytechnic University of the Philippines
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL -->
                <div class="flex min-h-[680px] items-center justify-center bg-white px-10 py-12">
                    <div class="w-full max-w-md">
                        <div class="text-center">
                            <img
                                src="/images/cperas-logo.webp"
                                alt="PUP Logo"
                                class="mx-auto h-28 w-28 object-contain"
                            />

                            <h1 class="mt-8 text-4xl font-black text-[rgb(var(--pup-maroon))]">
                                Welcome Back
                            </h1>

                            <p class="mt-3 text-sm text-muted-foreground">
                                Sign in to continue to your dashboard.
                            </p>
                        </div>

                        <div class="my-8 flex items-center gap-4">
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                            <div class="h-3 w-3 rotate-45 bg-[rgb(var(--pup-gold))]" />
                            <div class="h-px flex-1 bg-[rgb(var(--pup-gold-light))]" />
                        </div>

                        <div
                            v-if="status"
                            class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="space-y-2">
                                <Label for="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    v-model="form.email"
                                    placeholder="Enter your email"
                                    class="h-12 rounded-xl bg-white"
                                />

                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label for="password">Password</Label>

                                    <TextLink
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm font-semibold text-[rgb(var(--pup-maroon))]"
                                    >
                                        Forgot password?
                                    </TextLink>
                                </div>

                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    v-model="form.password"
                                    placeholder="Enter your password"
                                    class="h-12 rounded-xl bg-white"
                                />

                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="flex items-center space-x-3">
                                <Checkbox id="remember" v-model:checked="form.remember" />

                                <Label for="remember" class="cursor-pointer text-sm font-normal text-muted-foreground">
                                    Remember me
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-xl bg-[rgb(var(--pup-maroon))] font-bold text-white hover:bg-[rgb(var(--pup-maroon-dark))]"
                                :disabled="form.processing"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />

                                Log in
                            </Button>
                        </form>

                        <p class="mt-7 text-center text-sm text-muted-foreground">
                            Don’t have an account?

                            <TextLink
                                :href="route('register')"
                                class="font-bold text-[rgb(var(--pup-maroon))]"
                            >
                                Sign up
                            </TextLink>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthBase>
</template>