import { enableAutoUnmount, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';

import CheckboxField from '@admin/components/ui/CheckboxField.vue';
import SelectField from '@admin/components/ui/SelectField.vue';
import TextareaField from '@admin/components/ui/TextareaField.vue';
import TextField from '@admin/components/ui/TextField.vue';

enableAutoUnmount(afterEach);

/** Texts that screen readers read with the control: its label and everything in aria-describedby. */
function described(wrapper: VueWrapper, selector: string) {
    const control = wrapper.find(selector);
    const id = control.attributes('id');
    const describedBy = control.attributes('aria-describedby');

    return {
        label: wrapper.find(`label[for="${id}"]`).text(),
        descriptions: describedBy ? describedBy.split(' ').map((ref) => wrapper.find(`#${ref}`).text()) : [],
        invalid: control.attributes('aria-invalid'),
    };
}

describe('TextField', () => {
    it('links label, hint and error to the input', () => {
        const wrapper = mount(TextField, {
            props: { modelValue: '', label: 'Cena (zł)', hint: 'Np. 49,99', error: 'Podaj cenę.' },
        });

        expect(described(wrapper, 'input')).toEqual({
            label: 'Cena (zł)',
            descriptions: ['Np. 49,99', 'Podaj cenę.'],
            invalid: 'true',
        });
    });

    it('is not described or invalid without hint and error', () => {
        const wrapper = mount(TextField, { props: { modelValue: '', label: 'Nazwa' } });

        expect(described(wrapper, 'input')).toEqual({ label: 'Nazwa', descriptions: [], invalid: undefined });
    });

    it('updates the model and shows a unit after the input', async () => {
        const wrapper = mount(TextField, {
            props: { modelValue: '', label: 'Cena', inputmode: 'decimal', suffix: 'zł' },
        });

        await wrapper.find('input').setValue('49,99');

        expect(wrapper.emitted('update:modelValue')).toEqual([['49,99']]);
        expect(wrapper.find('input').attributes('inputmode')).toBe('decimal');
        expect(wrapper.text()).toContain('zł');
    });

    it('gives every field in a form its own id', () => {
        const Form = defineComponent(() => () => [
            h(TextField, { modelValue: '', label: 'A' }),
            h(TextField, { modelValue: '', label: 'B' }),
        ]);
        const ids = mount(Form)
            .findAll('input')
            .map((input) => input.attributes('id'));

        expect(new Set(ids).size).toBe(2);
    });
});

describe('TextareaField', () => {
    it('links label and error to the textarea and updates the model', async () => {
        const wrapper = mount(TextareaField, { props: { modelValue: '', label: 'Opis', error: 'Za długi.' } });

        expect(described(wrapper, 'textarea')).toEqual({
            label: 'Opis',
            descriptions: ['Za długi.'],
            invalid: 'true',
        });

        await wrapper.find('textarea').setValue('Nowy opis');
        expect(wrapper.emitted('update:modelValue')).toEqual([['Nowy opis']]);
    });
});

describe('SelectField', () => {
    const options = [
        { value: '1', label: 'Dom' },
        { value: '2', label: 'Ogród' },
    ];

    it('lists options after an optional empty one', () => {
        const wrapper = mount(SelectField, {
            props: { modelValue: '', label: 'Kategoria', options, placeholder: 'Wszystkie kategorie' },
        });

        expect(wrapper.findAll('option').map((o) => [o.attributes('value'), o.text()])).toEqual([
            ['', 'Wszystkie kategorie'],
            ['1', 'Dom'],
            ['2', 'Ogród'],
        ]);
        expect(described(wrapper, 'select').label).toBe('Kategoria');
    });

    it('has no empty option without a placeholder', () => {
        const wrapper = mount(SelectField, { props: { modelValue: '1', label: 'Kategoria', options } });

        expect(wrapper.findAll('option')).toHaveLength(2);
    });

    it('updates the model and shows errors', async () => {
        const wrapper = mount(SelectField, {
            props: { modelValue: '1', label: 'Kategoria', options, error: 'Wybierz kategorię.' },
        });

        await wrapper.find('select').setValue('2');

        expect(wrapper.emitted('update:modelValue')).toEqual([['2']]);
        expect(described(wrapper, 'select').descriptions).toEqual(['Wybierz kategorię.']);
    });
});

describe('CheckboxField', () => {
    it('links label, hint and error to the checkbox and updates the model', async () => {
        const wrapper = mount(CheckboxField, {
            props: { modelValue: false, label: 'Aktywny', hint: 'Widoczny w sklepie.', error: 'Błąd.' },
        });

        expect(described(wrapper, 'input')).toEqual({
            label: 'Aktywny',
            descriptions: ['Widoczny w sklepie.', 'Błąd.'],
            invalid: 'true',
        });

        await wrapper.find('input').setValue(true);
        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('is not invalid without an error', () => {
        const wrapper = mount(CheckboxField, { props: { modelValue: false, label: 'Aktywny' } });

        expect(described(wrapper, 'input')).toEqual({ label: 'Aktywny', descriptions: [], invalid: undefined });
    });

    it('can be disabled', () => {
        const wrapper = mount(CheckboxField, { props: { modelValue: true, label: 'Administrator', disabled: true } });

        expect(wrapper.find('input').attributes('disabled')).toBeDefined();
    });
});
