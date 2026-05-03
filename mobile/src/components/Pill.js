import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors } from '../theme/colors';

export default function Pill({ label, tone = 'default' }) {
  return (
    <View style={[styles.pill, tone === 'success' ? styles.success : null, tone === 'warning' ? styles.warning : null]}>
      <Text style={[styles.text, tone === 'warning' ? styles.warningText : null]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: colors.sky,
  },
  success: {
    backgroundColor: '#d8f3dc',
  },
  warning: {
    backgroundColor: '#fff3cd',
  },
  text: {
    color: colors.deep,
    fontWeight: '700',
    fontSize: 12,
  },
  warningText: {
    color: '#7f5539',
  },
});
