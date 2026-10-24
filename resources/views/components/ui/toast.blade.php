{{--
    The notification area. Any component raises one with `$this->dispatch('notify', message: '...')`
    (optionally `tone: 'danger'`); it disappears by itself after a few seconds.
--}}
<div
    class="ui-toasts"
    aria-live="polite"
    x-data="{
        toasts: [],
        add(detail) {
            const toast = { id: Date.now() + Math.random(), message: detail.message ?? '', tone: detail.tone ?? 'success' };
            this.toasts.push(toast);
            setTimeout(() => { this.toasts = this.toasts.filter((item) => item.id !== toast.id); }, 4000);
        },
    }"
    x-on:notify.window="add($event.detail)"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div class="ui-toast ui-glass ui-glass-menu" x-bind:class="{ 'ui-toast-danger': toast.tone === 'danger' }" x-text="toast.message"></div>
    </template>
</div>
