import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import DesktopRequirementScreen from '@/components/DesktopRequirementScreen.vue';
import { ref } from 'vue';

vi.mock('@/composables/useDesktop', () => ({
  useDesktop: () => ({
    isDesktop: ref(false),
  }),
}));

describe('DesktopRequirementScreen', () => {
  it('renders when viewport < 1200px', () => {
    const wrapper = mount(DesktopRequirementScreen);
    expect(wrapper.text()).toContain('Desktop Required');
  });

  it('mentions minimum width requirement', () => {
    const wrapper = mount(DesktopRequirementScreen);
    expect(wrapper.text()).toContain('1200');
  });

  it('has proper layout structure', () => {
    const wrapper = mount(DesktopRequirementScreen);
    const container = wrapper.find('.flex.h-screen');
    expect(container.exists()).toBe(true);
  });
});
