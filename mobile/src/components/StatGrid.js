import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors } from '../theme/colors';

export default function StatGrid({ items }) {
  return (
    <View style={styles.grid}>
      {items.map((item) => (
        <View key={item.label} style={styles.card}>
          <Text style={styles.value}>{item.value}</Text>
          <Text style={styles.label}>{item.label}</Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginHorizontal: -6,
    marginBottom: 8,
  },
  card: {
    width: '50%',
    paddingHorizontal: 6,
    marginBottom: 12,
  },
  value: {
    backgroundColor: colors.white,
    borderRadius: 20,
    overflow: 'hidden',
    paddingTop: 20,
    paddingHorizontal: 18,
    fontSize: 28,
    fontWeight: '800',
    color: colors.ink,
  },
  label: {
    backgroundColor: colors.white,
    paddingHorizontal: 18,
    paddingBottom: 18,
    borderBottomLeftRadius: 20,
    borderBottomRightRadius: 20,
    color: colors.slate,
    fontSize: 13,
  },
});
