import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors } from '../theme/colors';

export default function DropdownField({ label, value, options, placeholder = 'Select an option', onChange }) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => String(option.value) === String(value));

  return (
    <View style={styles.wrapper}>
      <Text style={styles.label}>{label}</Text>
      <Pressable style={styles.control} onPress={() => setOpen((current) => !current)}>
        <Text style={[styles.value, !selected ? styles.placeholder : null]}>{selected?.label || placeholder}</Text>
        <Text style={styles.chevron}>{open ? '^' : 'v'}</Text>
      </Pressable>
      {open ? (
        <View style={styles.menu}>
          {options.map((option) => (
            <Pressable
              key={String(option.value)}
              style={[styles.option, String(option.value) === String(value) ? styles.selectedOption : null]}
              onPress={() => {
                onChange(option.value);
                setOpen(false);
              }}
            >
              <Text style={styles.optionText}>{option.label}</Text>
            </Pressable>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    marginBottom: 14,
  },
  label: {
    marginBottom: 6,
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
  },
  control: {
    minHeight: 48,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    paddingHorizontal: 14,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  value: {
    flex: 1,
    color: colors.ink,
  },
  placeholder: {
    color: '#98a2b3',
  },
  chevron: {
    color: '#667085',
    fontSize: 12,
    marginLeft: 8,
  },
  menu: {
    marginTop: 6,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    overflow: 'hidden',
    backgroundColor: colors.white,
  },
  option: {
    minHeight: 44,
    justifyContent: 'center',
    paddingHorizontal: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  selectedOption: {
    backgroundColor: '#eef4ff',
  },
  optionText: {
    color: colors.ink,
    fontWeight: '600',
  },
});
