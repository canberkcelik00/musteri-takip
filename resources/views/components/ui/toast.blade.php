{{--
    The notification area. Any component raises one with `$this->dispatch('notify', message: '...')`
    (optionally `tone: 'danger'`); it disappears by itself after a few seconds. With
    `action: ['label' => '...', 'event' => '...', 'params' => [...]]` it carries one text button that dispatches that
    event and closes the notification; such a notification stays longer.
--}}
<div
    class="ui-toasts"
    aria-live="polite"
    x-data="{
        toasts: [],
        add(detail) {
            const action = detail.action ?? null;
            const toast = { id: Date.now() + Math.random(), message: detail.message ?? '', tone: detail.tone ?? 'success', action };
            this.toasts.push(toast);
            setTimeout(() => this.remove(toast), action ? 10000 : 4000);
        },
        remove(toast) {
            this.toasts = this.toasts.filter((item) => item.id !== toast.id);
        },
        run(toast) {
            this.remove(toast);
            this.$dispatch(toast.action.event, toast.action.params ?? {});
        },
    }"
    x-on:notify.window="add($event.detail)"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div class="ui-toast ui-glass ui-glass-menu" x-bind:class="{ 'ui-toast-danger': toast.tone === 'danger' }">
            <span x-text="toast.message"></span>
            <template x-if="toast.action">
                <button type="button" class="ui-toast-action" x-text="toast.action.label" x-on:click="run(toast)"></button>
            </template>
        </div>
    </template>
</div>
