<div>
    <input type="file" class="form-control" id="{{ $id ?? $name }}" name="{{ $name }}"
        data-image-url="{{ $imageUrl ?? '' }}" disabled="{{ $disabled ?? 'false' }}"
        multiple="{{ $multiple ?? 'false' }}" />
</div>
<script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        FilePond.registerPlugin(
            FilePondPluginImagePreview,
            FilePondPluginFileValidateType
        );

        const input = document.querySelector('[name="{{ $name }}"]');

        if (input) {
            let imageUrl = input.getAttribute('data-image-url');

            FilePond.create(input, {
                allowImagePreview: true,
                instantUpload: false,
                acceptedFileTypes: ['image/*'],
                credits: false,
                storeAsFile: true,
                files: imageUrl ? [{
                    source: imageUrl,
                    options: {
                        type: 'remote'
                    }
                }] : []
            });
        }
    });
</script>
