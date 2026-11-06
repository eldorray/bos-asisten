type PromptPasang = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const KUNCI_DITOLAK = 'spj.pasangDitolak';

let prompt = $state<PromptPasang | null>(null);
let perluPetunjukIos = $state(false);
let terbuka = $state(false);

function ajakSekaliDiHp(): void {
    if (
        localStorage.getItem(KUNCI_DITOLAK) === null &&
        window.matchMedia('(max-width: 767px)').matches
    ) {
        terbuka = true;
    }
}

export const pasangAplikasi = {
    get tersedia() {
        return prompt !== null || perluPetunjukIos;
    },
    get perluPetunjukIos() {
        return perluPetunjukIos;
    },
    get terbuka() {
        return terbuka;
    },

    buka(): void {
        terbuka = true;
    },

    tutup(): void {
        terbuka = false;
        localStorage.setItem(KUNCI_DITOLAK, '1');
    },

    async pasang(): Promise<void> {
        const ajakan = prompt;

        this.tutup();
        await ajakan?.prompt();
        await ajakan?.userChoice;
        prompt = null;
    },

    mulai(): void {
        const terpasang =
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone ===
                true;

        if (terpasang) {
            return;
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            prompt = event as PromptPasang;
            ajakSekaliDiHp();
        });
        window.addEventListener('appinstalled', () => {
            prompt = null;
            terbuka = false;
        });

        // Safari di iOS tidak punya ajakan pasang; caranya harus dijelaskan.
        if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            perluPetunjukIos = true;
            ajakSekaliDiHp();
        }
    },
};
