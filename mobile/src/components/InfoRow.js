import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

export default function InfoRow({ label, value, icon }) {
  return (
    <View style={styles.row}>
      <View style={styles.labelGroup}>
        {icon ? <FontAwesome5 name={icon} size={13} solid color={colors.slate} /> : null}
        <Text style={styles.label}>{label}</Text>
      </View>
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
  labelGroup: {
    flex: 1,
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  label: {
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
