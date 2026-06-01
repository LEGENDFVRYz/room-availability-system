<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const user = computed(() => (usePage().props as any).auth?.user);

// Real-time clock logic
const currentTime = ref('');
let timer: ReturnType<typeof setInterval>;

onMounted(() => {
  const updateTime = () => {
    currentTime.value = new Date().toLocaleTimeString('en-US', { 
      hour12: true, 
      hour: '2-digit', 
      minute: '2-digit', 
      second: '2-digit' 
    });
  };
  updateTime();
  timer = setInterval(updateTime, 1000);
});

onUnmounted(() => {
  clearInterval(timer);
});

// PUP Seal SVG Logo
// const pupLogo = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%237B1C2E' stroke='%23F0B429' stroke-width='3'/%3E%3Ctext x='50' y='45' text-anchor='middle' fill='%23F0B429' font-size='16' font-family='Georgia' font-weight='bold'%3EPUB%3C/text%3E%3Ctext x='50' y='62' text-anchor='middle' fill='%23fff' font-size='7' font-family='Georgia'%3EMANILA%3C/text%3E%3C/svg%3E`;
</script>

<template>
  <Head title="Computer Engineering Room System — PUP Manila" />

  <!-- Removed padding classes (p-4 sm:p-8 md:p-12) from this main wrapper -->
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 flex flex-col relative overflow-x-hidden selection:bg-[#7B1C2E]/10">
    
    <div class="absolute inset-0 pointer-events-none z-0 opacity-35">
      <div class="absolute left-[12%] top-0 bottom-0 w-[1px] bg-slate-200"></div>
      <div class="absolute right-[12%] top-0 bottom-0 w-[1px] bg-slate-200"></div>
    </div>

    <!-- Added inner wrapper for padding to constrain nav and main, but leave footer out -->
    <div class="flex flex-col flex-1 p-4 sm:p-8 md:p-12 relative z-10">
      <nav class="w-full max-w-6xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-slate-200 relative z-10 text-xs font-bold text-slate-400">
        <div class="flex items-center gap-3">
          <img src="/images/pup-logo.png" alt="PUP Watermark" class="h-8 w-8 object-contain drop-shadow-sm" />
          <span class="tracking-wider text-[#7B1C2E] uppercase font-black">PUP-MNL <span class="text-slate-300 font-normal mx-1">|</span> College of Engineering</span>
        </div>
        
        <div class="flex items-center gap-4 w-full sm:w-auto justify-between sm:justify-end border-t sm:border-t-0 border-slate-200 pt-3 sm:pt-0">
          <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200/60 px-2.5 py-1 rounded-md text-emerald-800 text-[10px] uppercase tracking-wider">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            LIVE PORTAL
          </div>
          <span class="text-slate-300 hidden sm:inline">|</span>
          <span class="font-mono text-slate-600 bg-white border border-slate-200 px-2.5 py-1 rounded-md shadow-sm tracking-wide" v-text="currentTime || '00:00:00 AM'"></span>
        </div>
      </nav>

      <main class="w-full max-w-5xl mx-auto flex-1 flex flex-col justify-center py-12 md:py-16 relative z-10 space-y-12">
        
        <header class="max-w-3xl space-y-3 pl-1 text-left">
          <span class="text-[10px] uppercase tracking-[0.2em] font-black text-[#7B1C2E] bg-rose-50 border border-rose-100 px-2.5 py-0.5 rounded-md inline-block">
            Computer Engineering Department
          </span>
          <h1 class="text-4xl sm:text-5xl md:text-[3.5rem] font-black text-slate-900 tracking-tight leading-[1.05]">
            Computer Engineering Room <br />
            <span class="bg-gradient-to-r from-[#7B1C2E] to-[#A3243C] bg-clip-text text-transparent">Availability & Scheduling System.</span>
          </h1>
          <p class="text-sm sm:text-base text-slate-500 leading-relaxed pt-1 font-medium max-w-xl">
            View real-time schedule grids for Computer Engineering classrooms, check room availability, or sign in to manage department configurations.
          </p>
        </header>

        <div class="w-full flex flex-col md:flex-row gap-6 items-stretch">
          
          <Link 
            :href="route('dashboard')" 
            class="w-full md:w-1/2 group relative bg-white border border-slate-200 rounded-[28px] p-8 md:p-10 shadow-sm transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-amber-500/5 hover:border-amber-400 text-left flex flex-col justify-between"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-[#D97706] bg-amber-50 px-2.5 py-0.5 rounded border border-amber-200/80">
                  Open Access
                </span>
              </div>
              <h2 class="text-2xl sm:text-3xl font-black text-slate-900 group-hover:text-[#D97706] transition-colors tracking-tight">
                Student Schedules
              </h2>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed font-medium">
                Look up active class times, check room availability, and view weekly timetables sorted by your specific section. No login required.
              </p>
            </div>
            
            <div class="inline-flex items-center gap-2.5 font-bold text-xs uppercase tracking-wider text-amber-950 bg-[#F0B429] hover:bg-[#D97706] px-5 py-3 rounded-xl transition-all duration-300 shadow-sm group-hover:scale-[1.02] self-start mt-8">
              <span>View Timetables</span>
              <span class="transform transition-transform group-hover:translate-x-1">➔</span>
            </div>
          </Link>

          <Link 
            :href="route('login')" 
            class="w-full md:w-1/2 group relative bg-gradient-to-br from-[#7B1C2E] to-[#5C1320] text-white rounded-[28px] p-8 md:p-10 shadow-sm transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-[#7B1C2E]/10 text-left flex flex-col justify-between overflow-hidden"
          >
            <div class="absolute -right-16 -top-16 w-36 h-36 bg-white/5 rounded-full blur-xl group-hover:bg-white/10 transition-all duration-300"></div>

            <div class="space-y-4 relative z-10">
              <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-rose-200 bg-white/10 px-2.5 py-0.5 rounded border border-white/10">
                  Admin Only
                </span>
              </div>
              <h2 class="text-2xl sm:text-3xl font-black text-white group-hover:text-rose-100 transition-colors tracking-tight">
                Admin Check-In
              </h2>
              <p class="text-xs sm:text-sm text-rose-100/80 leading-relaxed font-medium">
                Secure administrative portal for professors and authorized coordinators to assign classrooms, modify schedules, and manage active reservations.
              </p>
            </div>

            <div class="inline-flex items-center gap-2.5 font-bold text-xs uppercase tracking-wider text-slate-900 bg-white hover:bg-slate-50 px-5 py-3 rounded-xl transition-all duration-300 shadow-sm group-hover:scale-[1.02] self-start mt-8 relative z-10">
              <span>Sign In</span>
              <span class="transform transition-transform group-hover:translate-x-1">➔</span>
            </div>
          </Link>

        </div>

      </main>
    </div>

    <!-- Page footer -->
    <footer class="w-full mt-auto bg-pup-maroon-deep px-6 py-5 relative z-20">
      <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
          
          <div class="text-[11px] leading-relaxed text-white/50">
              <strong class="font-medium text-pup-gold-light">Polytechnic University of the Philippines - Manila</strong>
              <span class="hidden sm:inline"> · </span><br class="sm:hidden" />
              College of Engineering · Computer Engineering Department<br>
              CPE Room Availability &amp; Scheduling System · © June 2026
          </div>

          <div class="flex items-center gap-3 text-[11px] font-medium text-white/70">
                    
            <template v-if="user">
              <Link :href="route('logout')" method="post" :data="{ redirect_to: 'kiosk' }" as="button" class="hover:text-pup-gold transition-colors">
                  Go to Public View
              </Link>
              <span class="text-white/20">|</span>
              <Link :href="route('logout')" method="post" as="button" class="hover:text-white transition-colors">
                  Log Out
              </Link>
            </template>
            
            <template v-else>
              <div class="flex items-center gap-1.5">
                  <span class="text-white/40">Need staff access?</span>
                  <Link :href="route('register')" class="font-bold text-pup-gold hover:text-pup-gold-light transition-colors hover:underline underline-offset-2">
                      Register Here
                  </Link>
              </div>
              <span class="text-white/20">|</span>
              <Link :href="route('login')" class="font-bold hover:text-white transition-colors">
                  Admin Sign In
              </Link>
            </template>

        </div>
          
      </div>
  </footer>

  </div>
</template>

<style scoped>
@keyframes editorialReveal {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

main {
  animation: editorialReveal 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>