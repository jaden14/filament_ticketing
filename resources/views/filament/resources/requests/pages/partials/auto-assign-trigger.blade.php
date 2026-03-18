<div
    x-data
    x-init="
        $nextTick(() => {
            $wire.mountTableAction('assignReassign', '{{ $recordId }}');
        })
    "
></div>