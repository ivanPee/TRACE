import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors } from '../theme/colors';

export default function InfoRow({ label, value }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    gap: 12,
  },
  label: {
    flex: 1,
    color: colors.slate,
    fontSize: 14,
  },
  value: {
    flex: 1,
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
    textAlign: 'right',
  },
});
