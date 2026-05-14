import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

export default function AppButton({ label, onPress, variant = 'primary', icon }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.button, styles[variant], pressed && styles.pressed]}>
      <View style={styles.content}>
        {icon ? <FontAwesome5 name={icon} size={14} solid color={variant === 'ghost' ? colors.ink : colors.white} /> : null}
        <Text style={[styles.label, variant === 'ghost' ? styles.ghostLabel : null]}>{label}</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    minHeight: 52,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginBottom: 12,
  },
  primary: {
    backgroundColor: colors.ink,
  },
  secondary: {
    backgroundColor: colors.accent,
  },
  ghost: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.white,
  },
  pressed: {
    opacity: 0.85,
  },
  content: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
  },
  label: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '700',
  },
  ghostLabel: {
    color: colors.ink,
  },
});
