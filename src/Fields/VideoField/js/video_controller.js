import { createApp, h } from 'vue'
import VideoFieldEdit from '@orbit-video-field/Components/VideoFieldEdit.vue'

export default class extends window.Controller {
    static values = {
        withoutUpload: Boolean,
        withoutExists: Boolean,
        placeholder: String,
        maxResults: Number,
        ajaxUrl: String,
        recentUrl: String,
        group: String,
        name: String,
        storage: String,
        path: String,
        size: Number,
        uploadUrl: String,
        sortUrl: String,
        errorSize: String,
        errorType: String,
        initialValue: String
    }
    connect() {
        this.app = createApp({
            render: () => h(VideoFieldEdit, {
                inputName: this.nameValue,
                initialValue: this.initialValueValue,
                withoutUpload: this.withoutUploadValue,
                withoutExists: this.withoutExistsValue,
                placeholder: this.placeholderValue,
                maxResults: this.maxResultsValue,
                ajaxUrl: this.ajaxUrlValue,
                recentUrl: this.recentUrlValue,
                group: this.groupValue,
                storage: this.storageValue,
                path: this.pathValue,
                size: this.sizeValue,
                uploadUrl: this.uploadUrlValue,
                sortUrl: this.sortUrlValue,
                errorSize: this.errorSizeValue,
                errorType: this.errorTypeValue
            })
        })

        this.app.mount(this.element)
    }

    disconnect() {
        this.app?.unmount()
    }
}
