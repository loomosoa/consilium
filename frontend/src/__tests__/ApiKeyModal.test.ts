import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ApiKeyModal from '@/components/ApiKeyModal.vue'

describe('ApiKeyModal', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('does not store key in localStorage', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem')

    const wrapper = mount(ApiKeyModal, {
      props: { onStored: () => {}, onCancel: () => {} },
    })

    const input = wrapper.find('input')
    await input.setValue('sk-test-key-1234567890abcdef')

    const form = wrapper.find('form')
    await form.trigger('submit')

    // Key must never be written to localStorage
    expect(setItemSpy).not.toHaveBeenCalled()
  })

  it('shows error when submitting empty key', async () => {
    const wrapper = mount(ApiKeyModal, {
      props: { onStored: () => {}, onCancel: () => {} },
    })

    const form = wrapper.find('form')
    await form.trigger('submit')

    expect(wrapper.text()).toContain('API key is required')
  })

  it('emits cancel when cancel button clicked', async () => {
    const wrapper = mount(ApiKeyModal, {
      props: { onStored: () => {}, onCancel: () => {} },
    })

    const buttons = wrapper.findAll('button')
    const cancelButton = buttons.find((b) => b.text() === 'Cancel')
    await cancelButton?.trigger('click')

    expect(wrapper.emitted()).toHaveProperty('cancel')
  })

  it('uses type=password for key input', () => {
    const wrapper = mount(ApiKeyModal, {
      props: { onStored: () => {}, onCancel: () => {} },
    })

    const input = wrapper.find('input')
    expect(input.attributes('type')).toBe('password')
  })
})
